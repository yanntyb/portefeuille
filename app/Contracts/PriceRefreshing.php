<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface PriceRefreshing
{
    /**
     * Fetch prices for any securities missing a current price.
     * Returns true if a fetch was attempted.
     *
     * @param  Collection  $securities  Must have currentPrice already loaded.
     */
    public function refreshIfNeeded(Collection $securities): bool;
}
