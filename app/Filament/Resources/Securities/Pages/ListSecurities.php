<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\GainStatsOverview;
use App\Filament\Widgets\Securities\PerformanceStatsOverview;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Jobs\UpdateSecuritiesJob;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use App\Support\MarketCalendar;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

abstract class ListSecurities extends ListRecords
{
    use ExposesTableToWidgets;

    protected string $view = 'filament.resources.securities.pages.list-securities';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public bool $isUpdating = false;

    public function scopedSecuritiesQuery(): Builder
    {
        return Security::query()->forAccountType(static::getResource()::accountType(), auth()->id());
    }

    public function mount(): void
    {
        parent::mount();

        $cacheKey = UpdateSecuritiesJob::cacheKeyFor(static::getResource()::accountType()->value);
        $this->isUpdating = Cache::has($cacheKey);

        $this->computeSecurityVisibility();
        $this->js('$wire.refreshPrices()');
    }

    public function getTablePollingInterval(): ?string
    {
        return $this->isUpdating ? '5s' : null;
    }

    public function dehydrate(): void
    {
        $cacheKey = UpdateSecuritiesJob::cacheKeyFor(static::getResource()::accountType()->value);
        $wasUpdating = $this->isUpdating;
        $this->isUpdating = Cache::has($cacheKey);

        if ($wasUpdating && ! $this->isUpdating) {
            $this->computeSecurityVisibility();
            $this->dispatch('prices-updated');
        }
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

    public function getTitle(): string|Htmlable
    {
        $title = static::$title ?? static::getResource()::navigationLabel();

        return new HtmlString($title.' '.$this->getFormattedValuation());
    }

    private function getFormattedValuation(): string
    {
        $query = $this->scopedSecuritiesQuery();

        if ($this->shownSecurityIds) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $records = $query->with('latestPrice')->get();

        $valuation = $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $isPositive = $valuation >= $totalInvested;
        $colorClass = $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

        return '<span class="'.$colorClass.'">'.Number::currency($valuation, 'EUR').'</span>';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Livewire::make(PerformanceStatsOverview::class, [
                    'tablePageClass' => static::class,
                    'shownSecurityIds' => $this->shownSecurityIds,
                ])->key('performance-stats-overview'),
                Livewire::make(GainStatsOverview::class, [
                    'tablePageClass' => static::class,
                    'shownSecurityIds' => $this->shownSecurityIds,
                ])->key('gain-stats-overview'),
                Livewire::make(ValuationChartWidget::class, [
                    'tablePageClass' => static::class,
                    'shownSecurityIds' => $this->shownSecurityIds,
                ])->key('valuation-chart'),
                Section::make('Diversification')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->id('diversification')
                    ->extraAttributes(['class' => 'fi-section-no-content-padding'])
                    ->schema([
                        EmbeddedTable::make(),
                        Livewire::make(AllocationChartWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                        ])->key('allocation-chart'),
                        Livewire::make(SectorAllocationChartWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                        ])->key('sector-allocation-chart'),
                    ]),
            ]);
    }
}
