<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use Filament\Resources\Pages\EditRecord;

abstract class EditSecurity extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            SecurityForm::updateFromIsinAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $resource = static::getResource();

        if (! is_subclass_of($resource, AccountSecurityResource::class)) {
            return [];
        }

        return [
            SingleSecurityStatsOverview::make([
                'accountType' => $resource::accountType()->value,
            ]),
            SingleSecurityValuationChartWidget::make([
                'accountType' => $resource::accountType()->value,
            ]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
