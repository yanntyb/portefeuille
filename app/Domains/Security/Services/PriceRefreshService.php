<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Contracts\PriceRefreshing;
use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PriceRefreshService implements PriceRefreshing
{
    /**
     * Fetches prices for any securities missing a current price.
     * Returns true if a fetch was attempted.
     *
     * @param  Collection<int, Security>  $securities  Must have currentPrice already loaded.
     */
    public function refreshIfNeeded(Collection $securities): bool
    {
        $hasPriceless = $securities->contains(fn (Security $s) => $s->currentPrice === null);

        if (! $hasPriceless) {
            return false;
        }

        try {
            app(YahooFinanceService::class)->fetchAndStorePricesBulk($securities);
        } catch (\Throwable $e) {
            Log::warning('PriceRefreshService::refreshIfNeeded failed', ['error' => $e->getMessage()]);
        }

        return true;
    }
}
