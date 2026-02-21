<?php

namespace App\Filament\Resources\CtoSecurities\Pages;

use App\Filament\Resources\CtoSecurities\CtoSecurityResource;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCtoSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CtoSecurityResource::class;

    protected static ?string $title = 'CTO';

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
