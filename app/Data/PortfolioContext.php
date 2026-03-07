<?php

namespace App\Data;

use Illuminate\Support\Collection;

readonly class PortfolioContext
{
    /**
     * @param  Collection<int, \App\Models\Security>  $securities
     * @param  Collection<int, \App\Models\Transaction>  $transactions
     * @param  array<int, list<array{date: string, close: float}>>  $priceMap
     * @param  array<int, list<TimeSeriesPoint>>  $cumulativeQuantities
     */
    public function __construct(
        public Collection $securities,
        public Collection $transactions,
        public array $priceMap,
        public array $cumulativeQuantities,
    ) {}
}
