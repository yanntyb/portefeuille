<?php

namespace App\Infrastructure\Filament\Resources\Pages;

use App\Infrastructure\Filament\Resources\AssetResource;
use App\Infrastructure\Filament\Widgets\AssetPriceChartWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected string $view = 'asset-filament::view-asset';

    protected function getHeaderWidgets(): array
    {
        return [
            AssetPriceChartWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'assetId' => $this->record->id,
        ];
    }
}
