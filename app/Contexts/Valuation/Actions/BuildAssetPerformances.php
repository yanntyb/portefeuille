<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetPerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

class BuildAssetPerformances
{
    /** @var list<array{key: string, label: string, months: int}> */
    private const PERIODS = [
        ['key' => '1M', 'label' => '1 mois', 'months' => 1],
        ['key' => '3M', 'label' => '3 mois', 'months' => 3],
        ['key' => '6M', 'label' => '6 mois', 'months' => 6],
        ['key' => '1Y', 'label' => '1 an', 'months' => 12],
        ['key' => '2Y', 'label' => '2 ans', 'months' => 24],
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

        return array_map(function (array $period) use ($daily, $anchor): AssetPerformanceData {
            $boundary = $anchor->copy()->subMonthsNoOverflow($period['months'])->format('Y-m-d');

            return new AssetPerformanceData(
                key: $period['key'],
                label: $period['label'],
                pct: $this->calculator->returnOverWindow($daily, $boundary),
            );
        }, self::PERIODS);
    }
}
