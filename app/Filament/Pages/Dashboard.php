<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\DashboardGainStatsOverview;
use App\Filament\Widgets\Dashboard\DashboardPerformanceStatsOverview;
use App\Filament\Widgets\Dashboard\DashboardSectorAllocationChartWidget;
use App\Filament\Widgets\Dashboard\DashboardSecuritiesTableWidget;
use App\Filament\Widgets\Dashboard\PortfolioAllocationChartWidget;
use App\Models\Security;
use App\Models\Wallet;
use App\Services\DashboardDataProvider;
use App\Services\YahooFinanceService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Tableau de bord';

    /**
     * @return array{valuation: string, color: string}
     */
    public function getValuationData(): array
    {
        $provider = app(DashboardDataProvider::class);
        $wallets = Wallet::withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->get();

        $totalValuation = 0;
        $totalInvested = 0;

        foreach ($wallets as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            $totalValuation += $securities->sum(function ($security) {
                $close = $security->latestPrice?->close;

                if ($close === null || $security->total_quantity === null) {
                    return 0;
                }

                return (float) $security->total_quantity * (float) $close;
            });

            $totalInvested += $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));
        }

        return [
            'valuation' => Number::currency($totalValuation, 'EUR'),
            'color' => $totalValuation >= $totalInvested ? 'success' : 'danger',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Livewire::make(DashboardPerformanceStatsOverview::class)
                    ->key('dashboard-performance-stats'),
                Livewire::make(DashboardGainStatsOverview::class)
                    ->key('dashboard-gain-stats'),
                Section::make('Diversification')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->id('dashboard-diversification')
                    ->extraAttributes(['class' => 'fi-section-no-content-padding'])
                    ->schema([
                        Livewire::make(DashboardSecuritiesTableWidget::class)
                            ->key('dashboard-securities-table'),
                        Livewire::make(DashboardSectorAllocationChartWidget::class)
                            ->key('dashboard-sector-allocation-chart'),
                        Livewire::make(PortfolioAllocationChartWidget::class)
                            ->key('portfolio-allocation-chart'),
                    ]),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

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
