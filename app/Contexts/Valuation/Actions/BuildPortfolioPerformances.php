<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ScopedTransactions;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
        private ScopedTransactions $scoped,
    ) {}

    /**
     * Sans périmètre, tout le portefeuille. Avec, une ou plusieurs expositions, une enveloppe, ou
     * les deux.
     *
     * @return list<PerformanceData>
     */
    public function __invoke(int $userId, ?HoldingScope $scope = null): array
    {
        $scope ??= HoldingScope::all();

        /**
         * Le périmètre entre dans le nom retenu : une performance glissante agrège les
         * transactions avant d'en tirer ses fenêtres, elle ne se découpe donc pas après coup comme
         * une série par actif. Deux périmètres sous un même nom se serviraient le résultat l'un de
         * l'autre.
         */
        return $this->cache->remember(
            'performances'.$scope->cacheKey(),
            $userId,
            fn (): array => $this->build($userId, $scope),
        );
    }

    /** @return list<PerformanceData> */
    private function build(int $userId, HoldingScope $scope): array
    {
        $transactions = $this->scoped->within($this->transactions->forUser($userId), $scope);

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
