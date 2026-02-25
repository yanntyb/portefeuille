<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use Filament\Resources\Pages\EditRecord;

abstract class EditSecurity extends EditRecord
{
    public function getHeading(): string
    {
        return $this->record->name ?? parent::getHeading();
    }

    protected function getHeaderActions(): array
    {
        return [
            SecurityForm::updateFromIsinAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $resource = static::getResource();
        $isAccountResource = is_subclass_of($resource, AccountSecurityResource::class);

        $accountType = $isAccountResource
            ? $resource::accountType()->value
            : null;

        $widgets = [
            SingleSecurityStatsOverview::make([
                'accountType' => $accountType,
            ]),
            SingleSecurityPriceChartWidget::make(),
        ];

        if ($isAccountResource) {
            $widgets[] = SingleSecurityValuationChartWidget::make(['accountType' => $accountType]);
        }

        $widgets[] = SectorAllocationChartWidget::make();

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
