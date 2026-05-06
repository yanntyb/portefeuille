<?php

namespace App\Domains\Analytics\Contracts;

interface Rebalancing
{
    /**
     * Calculate buy/sell suggestions to rebalance portfolio to target allocation.
     * Returns array with items, remainder, total_invested.
     */
    public function calculate(array $securities, float $amountToInvest): array;
}
