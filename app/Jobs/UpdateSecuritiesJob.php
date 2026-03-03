<?php

namespace App\Jobs;

use App\Exceptions\TickerResolutionException;
use App\Services\YahooFinanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateSecuritiesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $securityIds
     */
    public function __construct(public array $securityIds) {}

    public function handle(YahooFinanceService $service): void
    {
        $securities = \App\Models\Security::whereIn('id', $this->securityIds)->get();

        $service->fetchAndStorePricesBulk($securities);

        foreach ($securities as $security) {
            try {
                $service->fetchAndStoreSectors($security);
            } catch (TickerResolutionException $e) {
                Log::warning("Sectors update skipped for {$security->name}: {$e->getMessage()}");
            }
        }
    }
}
