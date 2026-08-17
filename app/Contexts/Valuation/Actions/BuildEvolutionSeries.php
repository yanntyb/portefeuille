<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildEvolutionSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
    ) {}

    /** @param  ?int  $months  Profondeur de la fenêtre depuis aujourd'hui, null pour tout l'historique. */
    public function __invoke(
        int $userId,
        ?int $months = null,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): EvolutionSeriesData {
        /** La fenêtre et le pas font partie du résultat : ils font donc partie du nom retenu. */
        return $this->cache->remember(
            sprintf('evolution.%s.%s', $months ?? 'tout', $granularity->value),
            $userId,
            fn (): EvolutionSeriesData => $this->build($userId, $months, $granularity),
        );
    }

    private function build(
        int $userId,
        ?int $months,
        ValuationGranularity $granularity,
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

        $raw = $this->calculator->evolution($transactions, $prices, $months, $granularity);

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
