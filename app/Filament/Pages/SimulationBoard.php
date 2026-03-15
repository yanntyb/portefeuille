<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Simulation\SimulationBoardWidget;
use App\Filament\Widgets\Simulation\SimulationScenarioChartWidget;
use BackedEnum;
use Filament\Pages\Page;

class SimulationBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Simulation';

    protected static ?string $title = 'Simulation';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.simulation-board';

    protected function getHeaderWidgets(): array
    {
        return [
            SimulationBoardWidget::class,
            SimulationScenarioChartWidget::class,
        ];
    }
}
