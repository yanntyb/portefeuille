<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\PerformanceWindowData;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use Illuminate\Support\Carbon;

class ValuationCalculator
{
    /**
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     * @param  int  $maxPoints  plafond de points de la série (downsampling adaptatif)
     */
    public function calculate(array $transactions, array $prices, int $maxPoints = 200): ValuationSeriesData
    {
        $daily = $this->calculateDaily($transactions, $prices);
        $indices = self::downsampleIndices(count($daily->labels), $maxPoints);

        if (count($indices) === count($daily->labels)) {
            return $daily;
        }

        return new ValuationSeriesData(
            array_map(fn (int $i): string => $daily->labels[$i], $indices),
            array_map(fn (int $i): float => $daily->valuations[$i], $indices),
            array_map(fn (int $i): float => $daily->invested[$i], $indices),
            array_map(fn (int $i): float => $daily->prices[$i], $indices),
        );
    }

    /**
     * Série quotidienne pleine (un point par jour de prix), sans downsampling.
     *
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     */
    public function calculateDaily(array $transactions, array $prices): ValuationSeriesData
    {
        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $quantities */
        $quantities = [];
        /** @var list<array{date: string, value: float}> $investedSeries */
        $investedSeries = [];
        $buyQty = [];
        $buyCost = [];
        $totalInvested = 0.0;

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $quantities[$assetId] ??= [];

            $previous = end($quantities[$assetId]);
            $previousQty = $previous === false ? 0.0 : $previous['value'];
            $delta = $transaction->isSell ? -$transaction->quantity : $transaction->quantity;
            $quantities[$assetId][] = ['date' => $day, 'value' => $previousQty + $delta];

            if ($transaction->isSell) {
                $qty = $buyQty[$assetId] ?? 0.0;
                $cost = $buyCost[$assetId] ?? 0.0;
                $pru = $qty > 0.0 ? $cost / $qty : 0.0;
                $totalInvested -= $transaction->quantity * $pru - $transaction->fees;
            } else {
                $buyQty[$assetId] = ($buyQty[$assetId] ?? 0.0) + $transaction->quantity;
                $buyCost[$assetId] = ($buyCost[$assetId] ?? 0.0) + $transaction->quantity * $transaction->unitPrice;
                $totalInvested += $transaction->quantity * $transaction->unitPrice + $transaction->fees;
            }

            $investedSeries[] = ['date' => $day, 'value' => $totalInvested];
        }

        $days = collect($prices)->map(fn (PriceRecordData $p) => $p->date)->unique()->sort()->values()->all();
        $assetIds = array_keys($quantities);

        /** @var array<string, array<int, float>> $priceByDay */
        $priceByDay = [];
        foreach ($prices as $price) {
            $priceByDay[$price->date][$price->assetId] = $price->close;
        }

        $labels = [];
        $valuations = [];
        $invested = [];
        $unitPrices = [];
        $lastClose = [];
        $primaryAsset = $assetIds[0] ?? null;

        foreach ($days as $day) {
            $value = 0.0;
            foreach ($assetIds as $assetId) {
                if (isset($priceByDay[$day][$assetId])) {
                    $lastClose[$assetId] = $priceByDay[$day][$assetId];
                }
                $close = $lastClose[$assetId] ?? null;
                if ($close === null) {
                    continue;
                }
                $value += $this->valueAtDate($quantities[$assetId], $day) * $close;
            }

            $labels[] = $day;
            $valuations[] = round($value, 2);
            $invested[] = round($this->valueAtDate($investedSeries, $day), 2);
            $unitPrices[] = round($primaryAsset === null ? 0.0 : ($lastClose[$primaryAsset] ?? 0.0), 2);
        }

        return new ValuationSeriesData($labels, $valuations, $invested, $unitPrices);
    }

    /** @param  ?int  $months  Profondeur de la fenêtre depuis le dernier point, null pour tout l'historique. */
    public function windowAndAggregate(ValuationSeriesData $series, ?int $months, ValuationGranularity $granularity): ValuationSeriesData
    {
        if ($series->labels === []) {
            return $series;
        }

        $cutoff = $months === null
            ? null
            : Carbon::parse($series->labels[count($series->labels) - 1])->subMonthsNoOverflow($months)->format('Y-m-d');

        $labels = [];
        $valuations = [];
        $invested = [];
        $prices = [];

        foreach ($series->labels as $i => $label) {
            if ($cutoff !== null && $label < $cutoff) {
                continue;
            }
            $labels[] = $label;
            $valuations[] = $series->valuations[$i];
            $invested[] = $series->invested[$i];
            $prices[] = $series->prices[$i];
        }

        /** @var array<string, int> $lastIndexByBucket */
        $lastIndexByBucket = [];
        foreach ($labels as $i => $label) {
            $lastIndexByBucket[$granularity->bucketKey($label)] = $i;
        }

        $keep = array_values($lastIndexByBucket);
        sort($keep);

        return new ValuationSeriesData(
            array_map(fn (int $i): string => $labels[$i], $keep),
            array_map(fn (int $i): float => $valuations[$i], $keep),
            array_map(fn (int $i): float => $invested[$i], $keep),
            array_map(fn (int $i): float => $prices[$i], $keep),
        );
    }

    /**
     * Rendement de la position sur la fenêtre [$boundary, dernier jour], hors apports.
     * Le pourcentage est un TWR (time-weighted return) : les rendements quotidiens
     * (valeur_jour - valeur_veille - flux_jour) / valeur_veille sont enchaînés, donc la
     * date des apports n'influence pas le résultat. Les flux sont l'évolution de l'investi
     * cumulé, et les pas où la veille valait zéro sont ignorés. Retourne null si la série
     * ne remonte pas jusqu'à $boundary ou si la valeur de début est nulle.
     */
    public function returnOverWindow(ValuationSeriesData $daily, string $boundary): ?PerformanceWindowData
    {
        $startIndex = null;
        foreach ($daily->labels as $i => $label) {
            if ($label > $boundary) {
                break;
            }
            $startIndex = $i;
        }

        if ($startIndex === null) {
            return null;
        }

        $valueStart = $daily->valuations[$startIndex];

        if ($valueStart <= 0.0) {
            return null;
        }

        $last = count($daily->labels) - 1;
        $contributions = $daily->invested[$last] - $daily->invested[$startIndex];
        $pnl = ($daily->valuations[$last] - $valueStart) - $contributions;

        $growth = 1.0;

        for ($i = $startIndex + 1; $i <= $last; $i++) {
            $previousValue = $daily->valuations[$i - 1];

            if ($previousValue <= 0.0) {
                continue;
            }

            $flow = $daily->invested[$i] - $daily->invested[$i - 1];
            $growth *= 1 + ($daily->valuations[$i] - $previousValue - $flow) / $previousValue;
        }

        return new PerformanceWindowData(
            startDate: $daily->labels[$startIndex],
            valueStart: $valueStart,
            contributions: $contributions,
            pnl: $pnl,
            pct: round(($growth - 1) * 100, 4),
        );
    }

    /**
     * Fenêtre + agrège une série investi-par-titre : coupe les labels avant le cutoff
     * de la fenêtre, puis garde le dernier label de chaque bucket de granularité. Les valeurs
     * investies (cumulées) sont conservées telles quelles.
     *
     * @param  ?int  $months  Profondeur de la fenêtre depuis le dernier point, null pour tout l'historique.
     */
    public function windowAndAggregateInvested(InvestedByAssetSeriesData $series, ?int $months, ValuationGranularity $granularity): InvestedByAssetSeriesData
    {
        if ($series->labels === []) {
            return $series;
        }

        $cutoff = $months === null
            ? null
            : Carbon::parse($series->labels[count($series->labels) - 1])->subMonthsNoOverflow($months)->format('Y-m-d');

        /** @var array<string, int> $lastIndexByBucket */
        $lastIndexByBucket = [];
        foreach ($series->labels as $i => $label) {
            if ($cutoff !== null && $label < $cutoff) {
                continue;
            }
            $lastIndexByBucket[$granularity->bucketKey($label)] = $i;
        }

        $keep = array_values($lastIndexByBucket);
        sort($keep);

        return new InvestedByAssetSeriesData(
            array_map(fn (int $i): string => $series->labels[$i], $keep),
            array_map(
                fn (AssetInvestedSeriesData $serie): AssetInvestedSeriesData => new AssetInvestedSeriesData(
                    assetId: $serie->assetId,
                    name: $serie->name,
                    invested: array_map(fn (int $i): float => $serie->invested[$i], $keep),
                ),
                $series->series,
            ),
        );
    }

    /**
     * Perfs de position par période sur la série quotidienne : YTD, 1/3/6 mois, une ligne
     * par année pleine, puis Max depuis le premier jour de la série. Max remplace la
     * dernière année pleine, qui partirait presque du même jour. Les périodes que la
     * série ne couvre pas sont absentes du résultat.
     *
     * @return list<PerformanceData>
     */
    public function trailingPerformances(ValuationSeriesData $daily): array
    {
        if ($daily->labels === []) {
            return [];
        }

        $anchor = Carbon::parse($daily->labels[count($daily->labels) - 1]);
        $firstDay = $daily->labels[0];

        /** @var list<array{0: string, 1: string, 2: string}> $windows */
        $windows = [['YTD', 'YTD', $anchor->copy()->startOfYear()->format('Y-m-d')]];

        foreach ([['1M', '1 mois', 1], ['3M', '3 mois', 3], ['6M', '6 mois', 6]] as [$key, $label, $months]) {
            $windows[] = [$key, $label, $anchor->copy()->subMonthsNoOverflow($months)->format('Y-m-d')];
        }

        $fullYears = 0;
        while ($anchor->copy()->subYearsNoOverflow($fullYears + 1)->format('Y-m-d') >= $firstDay) {
            $fullYears++;
        }

        for ($year = 1; $year < $fullYears; $year++) {
            $windows[] = [
                $year.'Y',
                $year === 1 ? '1 an' : $year.' ans',
                $anchor->copy()->subYearsNoOverflow($year)->format('Y-m-d'),
            ];
        }

        $windows[] = ['MAX', 'Max', $firstDay];

        $performances = [];

        foreach ($windows as [$key, $label, $boundary]) {
            $window = $this->returnOverWindow($daily, $boundary);

            if ($window === null) {
                continue;
            }

            $performances[] = new PerformanceData(
                key: $key,
                label: $label,
                startDate: $window->startDate,
                valueStart: $window->valueStart,
                contributions: $window->contributions,
                gain: $window->pnl,
                pct: $window->pct,
            );
        }

        return $performances;
    }

    /**
     * Indices à conserver pour plafonner une série à $maxPoints points : pas régulier
     * adaptatif, premier et dernier points toujours inclus. Résultat trié croissant.
     *
     * @return list<int>
     */
    public static function downsampleIndices(int $count, int $maxPoints): array
    {
        if ($count <= 0) {
            return [];
        }

        $maxPoints = max($maxPoints, 1);

        if ($count <= $maxPoints) {
            return range(0, $count - 1);
        }

        $step = (int) ceil($count / $maxPoints);
        $indices = range(0, $count - 1, $step);
        $last = count($indices) - 1;

        if ($indices[$last] !== $count - 1) {
            $indices[$last] = $count - 1;
        }

        return $indices;
    }

    /**
     * Investi cumulé par asset dans le temps (fonction en escalier sur les dates
     * de transaction, sans prix). `name` est laissé à `#<assetId>` — l'action
     * qui consomme cette méthode y substitue le vrai nom.
     *
     * @param  list<TransactionRecordData>  $transactions
     */
    public function investedByAsset(array $transactions, int $maxPoints = 200): InvestedByAssetSeriesData
    {
        if ($transactions === []) {
            return InvestedByAssetSeriesData::empty();
        }

        $perAsset = $this->perAssetInvestedTimelines($transactions);

        /** @var array<string, true> $dates */
        $dates = [];
        foreach ($perAsset as $entries) {
            foreach ($entries as $entry) {
                $dates[$entry['date']] = true;
            }
        }

        $labels = array_keys($dates);
        sort($labels);

        $indices = self::downsampleIndices(count($labels), $maxPoints);
        if (count($indices) < count($labels)) {
            $labels = array_map(fn (int $i): string => $labels[$i], $indices);
        }

        $series = [];
        foreach ($perAsset as $assetId => $entries) {
            $series[] = new AssetInvestedSeriesData(
                assetId: $assetId,
                name: '#'.$assetId,
                invested: array_map(fn (string $day): float => round($this->valueAtDate($entries, $day), 2), $labels),
            );
        }

        return new InvestedByAssetSeriesData($labels, $series);
    }

    /**
     * Série d'évolution alignée : sur les labels de la valorisation windowée,
     * expose par asset la valeur de marché (quantité × prix, forward-fill) et l'investi.
     *
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     * @param  ?int  $months  Profondeur de la fenêtre depuis le dernier point, null pour tout l'historique.
     */
    public function evolution(
        array $transactions,
        array $prices,
        ?int $months,
        ValuationGranularity $granularity,
    ): EvolutionSeriesData {
        if ($transactions === []) {
            return EvolutionSeriesData::empty();
        }

        $daily = $this->calculateDaily($transactions, $prices);
        $windowed = $this->windowAndAggregate($daily, $months, $granularity);

        if ($windowed->labels === []) {
            return EvolutionSeriesData::empty();
        }

        $investedTimelines = $this->perAssetInvestedTimelines($transactions);
        $quantityTimelines = $this->perAssetQuantityTimelines($transactions);

        /** @var array<int, list<array{date: string, value: float}>> $priceTimelines */
        $priceTimelines = [];
        foreach ($prices as $price) {
            $priceTimelines[$price->assetId][] = ['date' => $price->date, 'value' => $price->close];
        }
        foreach ($priceTimelines as &$entries) {
            usort($entries, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        }
        unset($entries);

        $perAsset = [];
        foreach ($investedTimelines as $assetId => $investedEntries) {
            $quantities = $this->forwardFill($quantityTimelines[$assetId] ?? [], $windowed->labels);
            $closes = $this->forwardFill($priceTimelines[$assetId] ?? [], $windowed->labels);
            $invested = $this->forwardFill($investedEntries, $windowed->labels);

            $perAsset[] = new AssetSeriesData(
                assetId: $assetId,
                name: '#'.$assetId,
                value: array_map(
                    fn (float $quantity, float $close): float => round($quantity * $close, 2),
                    $quantities,
                    $closes,
                ),
                invested: array_map(fn (float $value): float => round($value, 2), $invested),
            );
        }

        return new EvolutionSeriesData(labels: $windowed->labels, perAsset: $perAsset);
    }

    /**
     * Timelines d'investi cumulé par asset (fonction en escalier sur les dates de
     * transaction), clé = assetId dans l'ordre d'apparition.
     *
     * @param  list<TransactionRecordData>  $transactions
     * @return array<int, list<array{date: string, value: float}>>
     */
    private function perAssetInvestedTimelines(array $transactions): array
    {
        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $perAsset */
        $perAsset = [];
        $buyQty = [];
        $buyCost = [];
        $invested = [];

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $invested[$assetId] ??= 0.0;
            $perAsset[$assetId] ??= [];

            if ($transaction->isSell) {
                $qty = $buyQty[$assetId] ?? 0.0;
                $cost = $buyCost[$assetId] ?? 0.0;
                $pru = $qty > 0.0 ? $cost / $qty : 0.0;
                $invested[$assetId] -= $transaction->quantity * $pru - $transaction->fees;
            } else {
                $buyQty[$assetId] = ($buyQty[$assetId] ?? 0.0) + $transaction->quantity;
                $buyCost[$assetId] = ($buyCost[$assetId] ?? 0.0) + $transaction->quantity * $transaction->unitPrice;
                $invested[$assetId] += $transaction->quantity * $transaction->unitPrice + $transaction->fees;
            }

            $perAsset[$assetId][] = ['date' => $day, 'value' => $invested[$assetId]];
        }

        return $perAsset;
    }

    /**
     * Timelines de quantité cumulée par asset (escalier sur les dates de transaction).
     *
     * @param  list<TransactionRecordData>  $transactions
     * @return array<int, list<array{date: string, value: float}>>
     */
    private function perAssetQuantityTimelines(array $transactions): array
    {
        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $quantities */
        $quantities = [];

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $quantities[$assetId] ??= [];
            $previous = end($quantities[$assetId]);
            $previousQty = $previous === false ? 0.0 : $previous['value'];
            $delta = $transaction->isSell ? -$transaction->quantity : $transaction->quantity;
            $quantities[$assetId][] = ['date' => $day, 'value' => $previousQty + $delta];
        }

        return $quantities;
    }

    /**
     * Valeur de l'escalier sur une suite de jours croissants, en un seul parcours.
     *
     * `valueAtDate` relit la timeline depuis le début à chaque jour demandé ; sur les séries
     * d'évolution — un millier de cours par actif, autant de labels — ce quadratique était le
     * poste le plus cher de la page. Les deux suites étant triées, un curseur qui n'avance
     * jamais en arrière suffit et rend exactement les mêmes valeurs.
     *
     * @param  list<array{date: string, value: float}>  $entries
     * @param  list<string>  $days  Jours croissants.
     * @return list<float>
     */
    private function forwardFill(array $entries, array $days): array
    {
        $values = [];
        $cursor = 0;
        $count = count($entries);
        $current = 0.0;

        foreach ($days as $day) {
            while ($cursor < $count && $entries[$cursor]['date'] <= $day) {
                $current = $entries[$cursor]['value'];
                $cursor++;
            }

            $values[] = $current;
        }

        return $values;
    }

    /**
     * @param  list<array{date: string, value: float}>  $series
     */
    private function valueAtDate(array $series, string $day): float
    {
        $value = 0.0;
        foreach ($series as $entry) {
            if ($entry['date'] > $day) {
                break;
            }
            $value = $entry['value'];
        }

        return $value;
    }
}
