<?php

namespace App\Domains\Analytics\Contracts;

use App\Domains\Portfolio\Models\Wallet;

interface Simulating
{
    /**
     * Run Monte Carlo simulation for portfolio projections.
     * Returns array with p10, p50, p90 percentile outcomes.
     */
    public function simulate(Wallet $wallet, array $params): array;
}
