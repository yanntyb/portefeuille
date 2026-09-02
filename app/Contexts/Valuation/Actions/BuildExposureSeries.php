<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ScopedTransactions;
use App\Contexts\Valuation\Services\ValuationCalculator;

/**
 * La valeur totale d'un périmètre du portefeuille dans le temps, au pas quotidien.
 *
 * Même chemin que `BuildPortfolioPerformances` avant ses fenêtres — les séries par actif de
 * `BuildEvolutionSeries` ne conviendraient pas : les sommer côté appelant rouvrirait l'idiome
 * « accumuler index par index » que le chantier de simplification vient de réduire.
 */
class BuildExposureSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
        private ScopedTransactions $scoped,
    ) {}

    public function __invoke(int $userId, ?HoldingScope $scope = null): ValuationSeriesData
    {
        $scope ??= HoldingScope::all();

        /**
         * Le périmètre entre dans le nom retenu : la série agrège les transactions avant
         * d'exister, elle ne se découpe pas après coup. Un nom réutilisé servirait une classe ou
         * une enveloppe à l'autre — `HoldingScope::cacheKey()` est la seule définition de ce
         * suffixe.
         */
        return $this->cache->remember(
            'exposition'.$scope->cacheKey(),
            $userId,
            fn (): ValuationSeriesData => $this->build($userId, $scope),
        );
    }

    private function build(int $userId, HoldingScope $scope): ValuationSeriesData
    {
        $transactions = $this->scoped->within($this->transactions->forUser($userId), $scope);

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $assetIds = array_values(array_unique(array_filter(array_map(
            fn ($transaction): ?int => $transaction->assetId,
            $transactions,
        ), fn (?int $assetId): bool => $assetId !== null)));

        return $this->calculator->calculateDaily(
            $transactions,
            $this->prices->forAssetsSince($assetIds, $transactions[0]->date),
        );
    }
}
