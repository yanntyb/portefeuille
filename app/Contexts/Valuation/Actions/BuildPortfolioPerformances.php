<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<PerformanceData> */
    public function __invoke(int $userId): array
    {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return [];
        }

        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->trailingPerformances($daily);
    }
}
