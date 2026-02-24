<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

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
            } catch (\Throwable $e) {
                Log::warning("Failed to update prices for {$security->name}: {$e->getMessage()}");
            }
        }

        foreach ($securities as $security) {
            $needsSectorRefresh = $security->sectors()
                ->where('updated_at', '>=', now()->subDays(7))
                ->doesntExist();

            if ($needsSectorRefresh) {
                try {
                    $service->fetchAndStoreSectors($security);
                } catch (\Throwable $e) {
                    Log::warning("Failed to update sectors for {$security->name}: {$e->getMessage()}");
                }
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

        $this->dispatch('prices-updated');
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
        return 4;
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
            SectorAllocationChartWidget::make([
                'tablePageClass' => static::class,
                'shownSecurityIds' => $this->shownSecurityIds,
            ]),
        ];
    }
}
