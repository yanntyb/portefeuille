<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;

class ValuationCalculator
{
    /**
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     * @param  int  $maxPoints  plafond de points de la série (downsampling adaptatif)
     */
    public function calculate(array $transactions, array $prices, int $maxPoints = 200): ValuationSeriesData
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
        $lastClose = [];

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
        }

        $indices = self::downsampleIndices(count($labels), $maxPoints);

        if (count($indices) < count($labels)) {
            $labels = array_map(fn (int $i): string => $labels[$i], $indices);
            $valuations = array_map(fn (int $i): float => $valuations[$i], $indices);
            $invested = array_map(fn (int $i): float => $invested[$i], $indices);
        }

        return new ValuationSeriesData($labels, $valuations, $invested);
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

        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $perAsset */
        $perAsset = [];
        $buyQty = [];
        $buyCost = [];
        $invested = [];
        /** @var array<string, true> $dates */
        $dates = [];

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $dates[$day] = true;
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
