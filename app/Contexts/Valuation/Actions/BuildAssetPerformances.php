<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetPerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

class BuildAssetPerformances
{
    /** @var list<array{key: string, label: string, months: int}> */
    private const MONTHLY_PERIODS = [
        ['key' => '1M', 'label' => '1 mois', 'months' => 1],
        ['key' => '3M', 'label' => '3 mois', 'months' => 3],
        ['key' => '6M', 'label' => '6 mois', 'months' => 6],
    ];

    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<AssetPerformanceData> */
    public function __invoke(int $userId, int $assetId): array
    {
        $transactions = array_values(array_filter(
            $this->transactions->forUser($userId),
            fn (TransactionRecordData $transaction) => $transaction->assetId === $assetId,
        ));

        if ($transactions === []) {
            return [];
        }

        $prices = $this->prices->forAssetsSince([$assetId], $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        if ($daily->labels === []) {
            return [];
        }

        $anchor = Carbon::parse($daily->labels[count($daily->labels) - 1]);

        $performances = [$this->yearToDate($daily, $anchor)];

        foreach (self::MONTHLY_PERIODS as $period) {
            $boundary = $anchor->copy()->subMonthsNoOverflow($period['months'])->format('Y-m-d');
            $performances[] = new AssetPerformanceData(
                key: $period['key'],
                label: $period['label'],
                pct: $this->calculator->returnOverWindow($daily, $boundary),
            );
        }

        foreach ($this->yearlyPeriods($daily, $anchor) as $performance) {
            $performances[] = $performance;
        }

        return $performances;
    }

    private function yearToDate(ValuationSeriesData $daily, Carbon $anchor): AssetPerformanceData
    {
        return new AssetPerformanceData(
            key: 'YTD',
            label: 'YTD',
            pct: $this->calculator->returnOverWindow($daily, $anchor->copy()->startOfYear()->format('Y-m-d')),
        );
    }

    /**
     * Une card par année pleine d'historique, jusqu'au premier investissement.
     *
     * @return list<AssetPerformanceData>
     */
    private function yearlyPeriods(ValuationSeriesData $daily, Carbon $anchor): array
    {
        $firstDay = $daily->labels[0];

        $fullYears = 0;
        while ($anchor->copy()->subYearsNoOverflow($fullYears + 1)->format('Y-m-d') >= $firstDay) {
            $fullYears++;
        }

        $performances = [];
        for ($year = 1; $year <= $fullYears; $year++) {
            $boundary = $anchor->copy()->subYearsNoOverflow($year)->format('Y-m-d');
            $performances[] = new AssetPerformanceData(
                key: $year.'Y',
                label: $year === 1 ? '1 an' : $year.' ans',
                pct: $this->calculator->returnOverWindow($daily, $boundary),
            );
        }

        return $performances;
    }
}
