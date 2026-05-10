<?php

namespace App\Domains\Analytics\Contracts;

use App\Domains\Asset\Models\Assets\Asset;

interface VolatilityCalculating
{
    /**
     * Calculate annualized volatility for a security based on historical prices.
     * Returns null if insufficient price data (< 30 prices).
     */
    public function forAsset(Asset $asset): ?float;

    /**
     * Calculate weighted portfolio volatility for a wallet.
     * Weight by market capitalization. Returns 15.0 if no securities.
     */
    public function forWallet(int $walletId, ?array $shownSecurityIds = null): float;
}
