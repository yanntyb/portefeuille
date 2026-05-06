<?php

namespace App\Domains\Portfolio\Contracts;

interface PortfolioPerformanceCalculating
{
    /**
     * Compute returns (TWR, daily valuations) for a collection of securities.
     * Returns array with daily breakdown, cumulative return, etc.
     */
    public function computeReturns(\Illuminate\Support\Collection $securities): array;
}
