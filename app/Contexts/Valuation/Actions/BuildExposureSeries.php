<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

/**
 * La valeur totale d'une exposition dans le temps, au pas quotidien.
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
        private InstrumentDirectoryPort $directory,
    ) {}

    /** @param  ?list<AssetClass>  $classes  Null pour tout le portefeuille. */
    public function __invoke(int $userId, ?array $classes = null): ValuationSeriesData
    {
        /**
         * Le filtre entre dans le nom retenu : la série agrège les transactions avant d'exister,
         * elle ne se découpe pas après coup. Un nom réutilisé servirait une classe à l'autre.
         */
        return $this->cache->remember(
            $classes === null ? 'exposition' : 'exposition.'.$this->nameOf($classes),
            $userId,
            fn (): ValuationSeriesData => $this->build($userId, $classes),
        );
    }

    /** @param  list<AssetClass>  $classes */
    private function nameOf(array $classes): string
    {
        return implode('-', array_map(fn (AssetClass $class): string => $class->value, $classes));
    }

    /** @param  ?list<AssetClass>  $classes */
    private function build(int $userId, ?array $classes): ValuationSeriesData
    {
        $transactions = $this->transactions->forUser($userId);

        /**
         * Le cash est global à l'utilisateur, pas à une exposition : un versement, un retrait ou
         * un dividende sans `asset_id` passe le filtre quelle que soit la classe demandée, sous
         * peine d'un cash construit sur les seuls achats et ventes de cette classe — négatif en
         * permanence, puisqu'il ne verrait jamais les versements qui les ont financés.
         */
        if ($classes !== null) {
            $kept = array_flip($this->directory->idsOfClasses($classes));
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $transaction->assetId === null || isset($kept[$transaction->assetId]),
            ));
        }

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $assetIds = array_values(array_unique(array_filter(array_map(
            fn (TransactionRecordData $transaction): ?int => $transaction->assetId,
            $transactions,
        ), fn (?int $assetId): bool => $assetId !== null)));

        return $this->calculator->calculateDaily(
            $transactions,
            $this->prices->forAssetsSince($assetIds, $transactions[0]->date),
        );
    }
}
