<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildInvestedByAssetSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): InvestedByAssetSeriesData {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return InvestedByAssetSeriesData::empty();
        }

        $raw = $this->calculator->investedByAsset($transactions, PHP_INT_MAX);

        $names = $this->directory->namesFor(array_map(
            fn (AssetInvestedSeriesData $serie): int => $serie->assetId,
            $raw->series,
        ));

        $series = array_map(
            fn (AssetInvestedSeriesData $serie): AssetInvestedSeriesData => new AssetInvestedSeriesData(
                assetId: $serie->assetId,
                name: $names[$serie->assetId] ?? $serie->name,
                invested: $serie->invested,
            ),
            $raw->series,
        );

        return $this->calculator->windowAndAggregateInvested(
            new InvestedByAssetSeriesData($raw->labels, $series),
            $range->months(),
            $granularity,
        );
    }
}
