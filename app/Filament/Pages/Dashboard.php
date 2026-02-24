<?php

namespace App\Filament\Pages;

use App\Models\Security;
use App\Models\SecuritySector;
use App\Services\YahooFinanceService;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Log;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function loadPrices(): void
    {
        $service = app(YahooFinanceService::class);

        $securitiesWithTicker = Security::query()
            ->whereHas('transactions')
            ->whereNotNull('ticker')
            ->get();

        $pricelessSecurities = $securitiesWithTicker->filter(
            fn (Security $security) => $security->todayPrice()->doesntExist()
        );

        foreach ($pricelessSecurities as $security) {
            try {
                $service->fetchAndStorePrices($security);
            } catch (\Throwable $e) {
                Log::warning("Failed to update prices for {$security->name}: {$e->getMessage()}");
            }
        }

        $securitiesNeedingSectors = $securitiesWithTicker->filter(
            fn (Security $security) => SecuritySector::query()
                ->where('security_id', $security->id)
                ->where('updated_at', '>=', now()->subDays(7))
                ->doesntExist()
        );

        foreach ($securitiesNeedingSectors as $security) {
            try {
                $service->fetchAndStoreSectors($security);
            } catch (\Throwable $e) {
                Log::warning("Failed to update sectors for {$security->name}: {$e->getMessage()}");
            }
        }

        if ($pricelessSecurities->isNotEmpty() || $securitiesNeedingSectors->isNotEmpty()) {
            $this->dispatch('prices-updated');
        }
    }
}
