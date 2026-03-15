<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityFeesStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPlusValueWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationStatsWidget;
use App\Jobs\UpdateSecurityJob;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

abstract class EditSecurity extends EditRecord
{
    protected string $view = 'filament.resources.securities.pages.edit-security';

    public bool $isUpdating = false;

    public function getHeading(): string
    {
        return $this->record->name ?? parent::getHeading();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->isUpdating = Cache::has(UpdateSecurityJob::cacheKeyFor($this->record->id));
    }

    public function checkUpdateStatus(): void
    {
        $wasUpdating = $this->isUpdating;
        $this->isUpdating = Cache::has(UpdateSecurityJob::cacheKeyFor($this->record->id));

        if ($wasUpdating && ! $this->isUpdating) {
            $this->record->refresh();
            $this->fillForm();
        }
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
            SingleSecurityPerformanceStatsOverview::make([
                'accountType' => $accountType,
            ]),
            SingleSecurityValuationStatsWidget::make([
                'accountType' => $accountType,
            ]),
            SingleSecurityPlusValueWidget::make([
                'accountType' => $accountType,
            ]),
        ];

        if ($isAccountResource) {
            $widgets[] = SingleSecurityValuationChartWidget::make(['accountType' => $accountType]);
        }

        $widgets[] = SingleSecurityPriceStatsWidget::make([
            'accountType' => $accountType,
        ]);
        $widgets[] = SingleSecurityPriceChartWidget::make();
        $widgets[] = SingleSecurityFeesStatsWidget::make([
            'accountType' => $accountType,
        ]);
        $widgets[] = SectorAllocationChartWidget::make();

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
