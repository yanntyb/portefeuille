<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildEvolutionSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): EvolutionSeriesData {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return EvolutionSeriesData::empty();
        }

        $since = $transactions[0]->date;
        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $since);

        $raw = $this->calculator->evolution($transactions, $prices, $range, $granularity);

        $names = $this->directory->namesFor(array_map(
            fn (AssetSeriesData $serie): int => $serie->assetId,
            $raw->perAsset,
        ));

        $perAsset = array_map(
            fn (AssetSeriesData $serie): AssetSeriesData => new AssetSeriesData(
                assetId: $serie->assetId,
                name: $names[$serie->assetId] ?? $serie->name,
                value: $serie->value,
                invested: $serie->invested,
            ),
            $raw->perAsset,
        );

        return new EvolutionSeriesData($raw->labels, $perAsset);
    }
}
