<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
        private InstrumentDirectoryPort $directory,
    ) {}

    /**
     * Sans `$classes`, tout le portefeuille. Avec, une ou plusieurs expositions.
     *
     * @param  ?list<AssetClass>  $classes
     * @return list<PerformanceData>
     */
    public function __invoke(int $userId, ?array $classes = null): array
    {
        /**
         * Le filtre entre dans le nom retenu : une performance glissante agrège les transactions
         * avant d'en tirer ses fenêtres, elle ne se découpe donc pas après coup comme une série
         * par actif. Deux classes sous un même nom se serviraient le résultat l'une de l'autre.
         */
        return $this->cache->remember(
            $classes === null ? 'performances' : 'performances.'.$this->nameOf($classes),
            $userId,
            fn (): array => $this->build($userId, $classes),
        );
    }

    /** @param  list<AssetClass>  $classes */
    private function nameOf(array $classes): string
    {
        return implode('-', array_map(fn (AssetClass $class): string => $class->value, $classes));
    }

    /**
     * @param  ?list<AssetClass>  $classes
     * @return list<PerformanceData>
     */
    private function build(int $userId, ?array $classes): array
    {
        $transactions = $this->transactions->forUser($userId);

        /**
         * Même raison que `BuildExposureSeries` : le cash est global à l'utilisateur, pas à une
         * exposition. Un versement, un retrait ou un dividende sans `asset_id` passe le filtre
         * quelle que soit la classe demandée.
         */
        if ($classes !== null) {
            $kept = array_flip($this->directory->idsOfClasses($classes));
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $transaction->assetId === null || isset($kept[$transaction->assetId]),
            ));
        }

        if ($transactions === []) {
            return [];
        }

        $assetIds = array_values(array_unique(array_filter(array_map(
            fn (TransactionRecordData $transaction): ?int => $transaction->assetId,
            $transactions,
        ), fn (?int $assetId): bool => $assetId !== null)));

        $prices = $this->prices->forAssetsSince($assetIds, $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->trailingPerformances($daily);
    }
}
