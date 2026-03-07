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
        $securities = Security::query()
            ->whereHas('transactions')
            ->whereNotNull('ticker')
            ->with('currentPrice')
            ->get();

        $pricelessSecurities = $securities->filter(fn (Security $security) => $security->currentPrice === null);

        if ($pricelessSecurities->isEmpty()) {
            return;
        }

        app(YahooFinanceService::class)->fetchAndStorePricesBulk($securities);

        $this->dispatch('prices-updated');
    }
}
