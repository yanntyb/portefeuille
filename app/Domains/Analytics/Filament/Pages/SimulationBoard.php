<?php

namespace App\Domains\Analytics\Filament\Pages;

use App\Filament\Widgets\Simulation\SimulationBoardWidget;
use App\Filament\Widgets\Simulation\SimulationScenarioChartWidget;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;

class SimulationBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Simulation';

    protected static ?string $title = 'Simulation';

    protected static string|\UnitEnum|null $navigationGroup = 'Outils';

    protected static ?int $navigationSort = 2;

    public static function registerNavigationItems(): void
    {
        Filament::getCurrentOrDefaultPanel()
            ->navigationItems(static::getNavigationItems());
    }

    public static function getNavigationItems(): array
    {
        $isAdmin = auth()->user()?->isAdmin();

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::$navigationGroup)
                ->icon(static::getNavigationIcon())
                ->sort(static::$navigationSort)
                ->badge(! $isAdmin ? 'Bientôt' : null, color: 'gray')
                ->url($isAdmin ? static::getUrl() : null)
                ->isActiveWhen(fn (): bool => $isAdmin && request()->routeIs(static::getRouteName())),
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    protected string $view = 'filament.pages.simulation-board';

    protected function getHeaderWidgets(): array
    {
        return [
            SimulationBoardWidget::class,
            SimulationScenarioChartWidget::class,
        ];
    }
}
