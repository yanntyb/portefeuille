<?php

namespace App\Contracts;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;

interface VolatilityCalculating
{
    /**
     * Calculate annualized volatility for a security based on historical prices.
     * Returns null if insufficient price data (< 30 prices).
     */
    public function forSecurity(Security $security): ?float;

    /**
     * Calculate weighted portfolio volatility for a wallet.
     * Weight by market capitalization. Returns 15.0 if no securities.
     */
    public function forWallet(Wallet $wallet, ?array $shownSecurityIds = null): float;
}
