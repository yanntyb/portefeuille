<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildAssetPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<PerformanceData> */
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

        return $this->calculator->trailingPerformances($daily);
    }
}
