<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioValuationSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): ValuationSeriesData {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $since = $transactions[0]->date;
        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $since);

        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->windowAndAggregate($daily, $range->months(), $granularity);
    }
}
