<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
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

    public function dehydrate(): void
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
            SingleSecurityStatsOverview::make([
                'accountType' => $accountType,
            ]),
            SingleSecurityPerformanceStatsOverview::make([
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
