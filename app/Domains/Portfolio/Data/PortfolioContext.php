<?php

namespace App\Domains\Portfolio\Data;

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Data\TimeSeriesPoint;
use Illuminate\Support\Collection;

readonly class PortfolioContext
{
    /**
     * @param  Collection<int, Security>  $securities
     * @param  Collection<int, Transaction>  $transactions
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
