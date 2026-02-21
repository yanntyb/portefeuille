<?php

namespace App\Filament\Resources\PeaSecurities\Pages;

use App\Filament\Resources\PeaSecurities\PeaSecurityResource;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListPeaSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PeaSecurityResource::class;

    protected static ?string $title = 'PEA';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityStatsOverview::make(['tablePageClass' => static::class]),
            ValuationChartWidget::make(['tablePageClass' => static::class]),
        ];
    }
}
