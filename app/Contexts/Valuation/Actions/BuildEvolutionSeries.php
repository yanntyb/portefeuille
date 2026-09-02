<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ScopedTransactions;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildEvolutionSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
        private ScopedTransactions $scoped,
    ) {}

    /**
     * @param  ?int  $months  Profondeur de la fenêtre depuis aujourd'hui, null pour tout l'historique.
     * @param  ?HoldingScope  $scope  Périmètre à garder, null pour tout le portefeuille.
     */
    public function __invoke(
        int $userId,
        ?int $months = null,
        ValuationGranularity $granularity = ValuationGranularity::Month,
        ?HoldingScope $scope = null,
    ): EvolutionSeriesData {
        $scope ??= HoldingScope::all();

        /**
         * Une enveloppe se découpe avant le cache, une exposition après, et les deux régimes ne
         * sont pas interchangeables : la série par actif porte de quoi retrouver la classe d'un
         * actif, jamais l'enveloppe qui le tient — un même titre acheté dans deux enveloppes n'a
         * qu'une ligne, et la filtrer après coup rendrait les quantités des deux. Le périmètre
         * entre donc dans le nom retenu, comme pour `BuildExposureSeries`.
         */
        if ($scope->walletId !== null) {
            return $this->cache->remember(
                sprintf('evolution.%s.%s%s', $months ?? 'tout', $granularity->value, $scope->cacheKey()),
                $userId,
                fn (): EvolutionSeriesData => $this->build($userId, $months, $granularity, $scope),
            );
        }

        /** La fenêtre et le pas font partie du résultat : ils font donc partie du nom retenu. */
        $series = $this->cache->remember(
            sprintf('evolution.%s.%s', $months ?? 'tout', $granularity->value),
            $userId,
            fn (): EvolutionSeriesData => $this->build($userId, $months, $granularity, HoldingScope::all()),
        );

        /**
         * Le filtre s'applique après le cache, et non dans la construction : la série par actif
         * porte déjà de quoi trier, donc une seule construction sert la page Actions et la page
         * Crypto. La grille de labels reste celle de tout le portefeuille — deux pages qui
         * partagent une abscisse se comparent.
         */
        return $scope->classes === null ? $series : $this->onlyClasses($series, $scope);
    }

    private function onlyClasses(EvolutionSeriesData $series, HoldingScope $scope): EvolutionSeriesData
    {
        $kept = array_flip($this->directory->idsOfClasses($scope->classes ?? []));

        return new EvolutionSeriesData(
            labels: $series->labels,
            perAsset: array_values(array_filter(
                $series->perAsset,
                fn (AssetSeriesData $asset): bool => isset($kept[$asset->assetId]),
            )),
        );
    }

    private function build(
        int $userId,
        ?int $months,
        ValuationGranularity $granularity,
        HoldingScope $scope,
    ): EvolutionSeriesData {
        $transactions = $this->scoped->within($this->transactions->forUser($userId), $scope);

        if ($transactions === []) {
            return EvolutionSeriesData::empty();
        }

        $since = $transactions[0]->date;
        $assetIds = array_values(array_unique(array_filter(array_map(
            fn (TransactionRecordData $transaction): ?int => $transaction->assetId,
            $transactions,
        ), fn (?int $assetId): bool => $assetId !== null)));

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
