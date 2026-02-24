<?php

namespace App\Filament\Pages;

use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function loadPrices(): void
    {
        $pricelessSecurities = Security::query()
            ->whereHas('transactions')
            ->whereNotNull('ticker')
            ->get()
            ->filter(fn (Security $security) => $security->todayPrice()->doesntExist());

        if ($pricelessSecurities->isEmpty()) {
            return;
        }

        app(YahooFinanceService::class)->fetchAndStorePricesBulk($pricelessSecurities);

        $this->dispatch('prices-updated');
    }
}
