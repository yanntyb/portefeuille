<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\DashboardCorrelationMatrixWidget;
use App\Filament\Widgets\Dashboard\DashboardGainStatsOverview;
use App\Filament\Widgets\Dashboard\DashboardPerformanceStatsOverview;
use App\Filament\Widgets\Dashboard\DashboardSectorAllocationChartWidget;
use App\Filament\Widgets\Dashboard\DashboardSecuritiesTableWidget;
use App\Filament\Widgets\Dashboard\DashboardValuationWidget;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Tableau de bord';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Livewire::make(DashboardValuationWidget::class)
                    ->key('dashboard-valuation'),
                Livewire::make(DashboardPerformanceStatsOverview::class)
                    ->key('dashboard-performance-stats'),
                Livewire::make(DashboardGainStatsOverview::class)
                    ->key('dashboard-gain-stats'),
                Section::make('Diversification')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->id('dashboard-diversification')
                    ->schema([
                        Livewire::make(DashboardSecuritiesTableWidget::class)
                            ->key('dashboard-securities-table'),
                        Livewire::make(DashboardSectorAllocationChartWidget::class)
                            ->key('dashboard-sector-allocation-chart'),
                        Livewire::make(DashboardCorrelationMatrixWidget::class)
                            ->key('dashboard-correlation-matrix'),
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

        try {
            app(YahooFinanceService::class)->fetchAndStorePricesBulk($securities);
        } catch (\Throwable $e) {
            Log::warning('Dashboard::loadPrices failed', ['error' => $e->getMessage()]);
        }

        $this->dispatch('prices-updated');
    }
}
