<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
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

    public function windowAndAggregate(ValuationSeriesData $series, ValuationRange $range, ValuationGranularity $granularity): ValuationSeriesData
    {
        if ($series->labels === []) {
            return $series;
        }

        $months = $range->months();
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
     * Rendement de la position sur la fenêtre [$boundary, dernier jour], hors apports
     * (Modified-Dietz simplifié) : (valeur_fin - valeur_début - apports) / valeur_début.
     * Les apports sont l'évolution de l'investi cumulé sur la fenêtre. Retourne null si
     * la série ne remonte pas jusqu'à $boundary ou si la valeur de début est nulle.
     */
    public function returnOverWindow(ValuationSeriesData $daily, string $boundary): ?float
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

        return $pnl / $valueStart * 100;
    }

    /**
     * Fenêtre + agrège une série investi-par-titre : coupe les labels avant le cutoff
     * du range, puis garde le dernier label de chaque bucket de granularité. Les valeurs
     * investies (cumulées) sont conservées telles quelles.
     */
    public function windowAndAggregateInvested(InvestedByAssetSeriesData $series, ValuationRange $range, ValuationGranularity $granularity): InvestedByAssetSeriesData
    {
        if ($series->labels === []) {
            return $series;
        }

        $months = $range->months();
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
     * Perfs de position par période sur la série quotidienne : YTD, 1/3/6 mois,
     * puis une card par année pleine jusqu'au premier jour de la série.
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

        $performances = [
            new PerformanceData('YTD', 'YTD', $this->returnOverWindow($daily, $anchor->copy()->startOfYear()->format('Y-m-d'))),
        ];

        foreach ([['1M', '1 mois', 1], ['3M', '3 mois', 3], ['6M', '6 mois', 6]] as [$key, $label, $months]) {
            $boundary = $anchor->copy()->subMonthsNoOverflow($months)->format('Y-m-d');
            $performances[] = new PerformanceData($key, $label, $this->returnOverWindow($daily, $boundary));
        }

        $fullYears = 0;
        while ($anchor->copy()->subYearsNoOverflow($fullYears + 1)->format('Y-m-d') >= $firstDay) {
            $fullYears++;
        }

        for ($year = 1; $year <= $fullYears; $year++) {
            $boundary = $anchor->copy()->subYearsNoOverflow($year)->format('Y-m-d');
            $performances[] = new PerformanceData(
                $year.'Y',
                $year === 1 ? '1 an' : $year.' ans',
                $this->returnOverWindow($daily, $boundary),
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
     */
    public function evolution(
        array $transactions,
        array $prices,
        ValuationRange $range,
        ValuationGranularity $granularity,
    ): EvolutionSeriesData {
        if ($transactions === []) {
            return EvolutionSeriesData::empty();
        }

        $windowed = $this->windowAndAggregate(
            $this->calculateDaily($transactions, $prices),
            $range,
            $granularity,
        );

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
            $qtyEntries = $quantityTimelines[$assetId] ?? [];
            $priceEntries = $priceTimelines[$assetId] ?? [];

            $perAsset[] = new AssetSeriesData(
                assetId: $assetId,
                name: '#'.$assetId,
                value: array_map(
                    fn (string $day): float => round($this->valueAtDate($qtyEntries, $day) * $this->valueAtDate($priceEntries, $day), 2),
                    $windowed->labels,
                ),
                invested: array_map(
                    fn (string $day): float => round($this->valueAtDate($investedEntries, $day), 2),
                    $windowed->labels,
                ),
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
