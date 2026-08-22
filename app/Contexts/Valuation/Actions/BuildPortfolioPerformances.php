<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Enums\InstrumentType;
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
     * Sans `$types`, tout le portefeuille. Avec, une seule classe d'actif.
     *
     * @param  ?list<InstrumentType>  $types
     * @return list<PerformanceData>
     */
    public function __invoke(int $userId, ?array $types = null): array
    {
        /**
         * Le filtre entre dans le nom retenu : une performance glissante agrège les transactions
         * avant d'en tirer ses fenêtres, elle ne se découpe donc pas après coup comme une série
         * par actif. Deux classes sous un même nom se serviraient le résultat l'une de l'autre.
         */
        return $this->cache->remember(
            $types === null ? 'performances' : 'performances.'.$this->nameOf($types),
            $userId,
            fn (): array => $this->build($userId, $types),
        );
    }

    /** @param  list<InstrumentType>  $types */
    private function nameOf(array $types): string
    {
        return implode('-', array_map(fn (InstrumentType $type): string => $type->value, $types));
    }

    /**
     * @param  ?list<InstrumentType>  $types
     * @return list<PerformanceData>
     */
    private function build(int $userId, ?array $types): array
    {
        $transactions = $this->transactions->forUser($userId);

        if ($types !== null) {
            $kept = array_flip($this->directory->idsOfTypes($types));
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => isset($kept[$transaction->assetId]),
            ));
        }

        if ($transactions === []) {
            return [];
        }

        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->trailingPerformances($daily);
    }
}
