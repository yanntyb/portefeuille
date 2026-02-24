<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Exceptions\TickerResolutionException;
use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

abstract class ListSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public function mount(): void
    {
        parent::mount();

        $allIds = static::getResource()::getEloquentQuery()
            ->pluck('securities.id')
            ->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', today()->subDays(4))
            ->pluck('security_id')
            ->unique()
            ->all();

        $this->shownSecurityIds = $idsWithPrice;
        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));

        $this->js('$wire.refreshPrices()');
    }

    public function refreshPrices(): void
    {
        $service = app(YahooFinanceService::class);
        $securities = static::getResource()::getEloquentQuery()->get();

        foreach ($securities as $security) {
            try {
                $service->fetchAndStorePrices($security);
            } catch (TickerResolutionException) {
                // Skip securities without resolvable ticker
            }
        }

        $allIds = $securities->pluck('id')->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', today()->subDays(4))
            ->pluck('security_id')
            ->unique()
            ->all();

        $this->shownSecurityIds = $idsWithPrice;
        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));
    }

    public function toggleSecurity(int $id): void
    {
        if (in_array($id, $this->shownSecurityIds)) {
            $this->shownSecurityIds = array_values(array_diff($this->shownSecurityIds, [$id]));
        } else {
            $this->shownSecurityIds[] = $id;
        }

        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SecurityStatsOverview::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
            ValuationChartWidget::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
            AllocationChartWidget::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
        ];
    }
}
