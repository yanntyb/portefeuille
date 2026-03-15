<?php

namespace App\Filament\Pages;

use App\Data\AccountPageData;
use App\Enums\AccountType;
use App\Extensions\Store;
use App\Filament\Resources\Securities\Tables\SecuritiesTable;
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
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;

abstract class AccountPage extends Page implements HasTable
{
    use ExposesTableToWidgets;
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected string $view = 'filament.pages.account-page';

    public ?string $activeTab = null;

    public ?Model $parentRecord = null;

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public bool $isUpdating = false;

    abstract public static function accountType(): AccountType;

    public function scopedSecuritiesQuery(): Builder
    {
        return Security::query()->forAccountType(static::accountType(), auth()->id());
    }

    public function mount(): void
    {
        $cacheKey = UpdateSecuritiesJob::cacheKeyFor(static::accountType()->value);
        $this->isUpdating = Cache::has($cacheKey);

        $this->computeSecurityVisibility();
        $this->restoreShownSecurityIds();

        Store::add('account', AccountPageData::from($this), persist: true);

        $this->js('$wire.refreshPrices()');
    }

    private function sessionKey(): string
    {
        return 'account_shown_ids_'.static::accountType()->value.'_'.auth()->id();
    }

    private function restoreShownSecurityIds(): void
    {
        $savedIds = session($this->sessionKey());

        if ($savedIds === null) {
            return;
        }

        $allIds = array_merge($this->shownSecurityIds, $this->pricelessSecurityIds);
        $this->shownSecurityIds = array_values(array_intersect($savedIds, $allIds));
    }

    public function getTablePollingInterval(): ?string
    {
        return $this->isUpdating ? '5s' : null;
    }

    public function dehydrate(): void
    {
        $cacheKey = UpdateSecuritiesJob::cacheKeyFor(static::accountType()->value);
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

        session([$this->sessionKey() => $this->shownSecurityIds]);

        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(static::getNavigationLabel().' '.$this->getFormattedValuation());
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

    public function table(Table $table): Table
    {
        return SecuritiesTable::configure(
            $table->query(fn (): Builder => Security::query()->forAccountType(static::accountType(), auth()->id()))
        );
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
