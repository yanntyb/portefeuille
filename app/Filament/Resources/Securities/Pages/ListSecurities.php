<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\PerformanceStatsOverview;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use App\Support\MarketCalendar;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

abstract class ListSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected string $view = 'filament.resources.securities.pages.list-securities';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public function scopedSecuritiesQuery(): Builder
    {
        return Security::query()->forAccountType(static::getResource()::accountType(), auth()->id());
    }

    public function mount(): void
    {
        parent::mount();

        $this->computeSecurityVisibility();
        $this->js('$wire.refreshPrices()');
    }

    private function computeSecurityVisibility(): void
    {
        $allIds = $this->scopedSecuritiesQuery()
            ->pluck('securities.id')
            ->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->pluck('security_id')
            ->unique()
            ->all();

        $this->shownSecurityIds = $idsWithPrice;
        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));
    }

    public function refreshPrices(): void
    {
        $securityIds = $this->scopedSecuritiesQuery()
            ->pluck('securities.id')
            ->all();

        $securities = Security::query()
            ->whereIn('id', $securityIds)
            ->whereNotNull('ticker')
            ->get();

        $hasPriceless = $securities->load('currentPrice')
            ->contains(fn (Security $s) => $s->currentPrice === null);

        if (! $hasPriceless) {
            $this->computeSecurityVisibility();
            $this->dispatch('prices-updated');

            return;
        }

        app(YahooFinanceService::class)->fetchAndStorePricesBulk($securities);

        $this->computeSecurityVisibility();
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
            PerformanceStatsOverview::make([
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
