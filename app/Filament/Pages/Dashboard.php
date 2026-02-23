<?php

namespace App\Filament\Pages;

use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Log;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function loadPrices(): void
    {
        $securities = Security::query()
            ->whereHas('transactions')
            ->whereNotNull('ticker')
            ->whereDoesntHave('todayPrice')
            ->get();

        if ($securities->isEmpty()) {
            return;
        }

        $service = app(YahooFinanceService::class);

        foreach ($securities as $security) {
            try {
                $service->fetchAndStorePrices($security);
            } catch (\Throwable $e) {
                Log::warning("Failed to update prices for {$security->name}: {$e->getMessage()}");
            }
        }

        $this->dispatch('prices-updated');
    }
}
