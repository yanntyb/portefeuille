<?php

namespace App\Domains\Security\Jobs;

use App\Domains\Security\Exceptions\TickerResolutionException;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Services\YahooFinanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateSecurityJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $securityId,
        public string $ticker,
        public string $name,
    ) {}

    public static function cacheKeyFor(int $securityId): string
    {
        return "updating-security:{$securityId}";
    }

    public function handle(YahooFinanceService $service): void
    {
        $cacheKey = self::cacheKeyFor($this->securityId);

        try {
            $security = Security::findOrFail($this->securityId);
            $security->update(['ticker' => $this->ticker, 'name' => $this->name]);

            $security->prices()->delete();
            $service->fetchAndStorePrices($security, new \DateTimeImmutable('-5 years'));

            $security->sectors()->delete();

            try {
                $service->fetchAndStoreSectors($security);
            } catch (TickerResolutionException $e) {
                Log::warning("Sectors update skipped for {$security->name}: {$e->getMessage()}");
            }
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
