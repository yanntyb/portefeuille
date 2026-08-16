<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildAssetValuationSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        int $assetId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): ValuationSeriesData {
        $transactions = array_values(array_filter(
            $this->transactions->forUser($userId),
            fn (TransactionRecordData $transaction) => $transaction->assetId === $assetId,
        ));

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $prices = $this->prices->forAssetsSince([$assetId], $transactions[0]->date);

        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->windowAndAggregate($daily, $range->months(), $granularity);
    }
}
