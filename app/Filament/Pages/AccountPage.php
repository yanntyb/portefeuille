<?php

namespace App\Filament\Pages;

use App\Concerns\HasTableStore;
use App\Contracts\TableStoreable;
use App\Data\AccountPageData;
use App\Filament\Resources\Securities\Tables\SecuritiesTable;
use App\Filament\Resources\WalletSecurities\WalletSecurityResource;
use App\Filament\Widgets\Securities\AllocationChartWidget;
use App\Filament\Widgets\Securities\GainStatsOverview;
use App\Filament\Widgets\Securities\PerformanceStatsOverview;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Jobs\UpdateSecuritiesJob;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\YahooFinanceService;
use App\Support\MarketCalendar;
use Filament\Actions\Action;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;

abstract class AccountPage extends Page implements HasTable, TableStoreable
{
    use ExposesTableToWidgets;
    use HasTableStore;
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected string $view = 'filament.pages.account-page';

    public ?string $activeTab = null;

    public ?Model $parentRecord = null;

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $hiddenSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public bool $isUpdating = false;

    public int $tableRecordLimit = 10;

    public ?Wallet $wallet = null;

    public function scopedSecuritiesQuery(): Builder
    {
        if ($this->wallet === null) {
            return Security::query()->forAuth()->whereRaw('0 = 1');
        }

        return Security::query()->forWallet($this->wallet);
    }

    public function mount(): void
    {
        if ($this->wallet === null) {
            return;
        }

        $cacheKey = UpdateSecuritiesJob::cacheKeyFor((string) $this->wallet->id);
        $this->isUpdating = Cache::has($cacheKey);

        $this->computeSecurityVisibility();

        $this->js('$wire.refreshPrices()');
    }

    public function tableStoreName(): string
    {
        return 'account';
    }

    public function toTableStore(): array
    {
        return AccountPageData::from($this)->toStore();
    }

    /** @param array<string, mixed> $data */
    public function fromTableStore(array $data): void
    {
        $hiddenIds = $data['hiddenSecurityIds'] ?? null;

        if ($hiddenIds === null) {
            return;
        }

        $allIds = array_merge($this->shownSecurityIds, $this->pricelessSecurityIds);
        $this->hiddenSecurityIds = array_values(array_intersect($hiddenIds, $allIds));
        $this->computeSecurityVisibility();
    }

    public function getTablePollingInterval(): ?string
    {
        return $this->isUpdating ? '5s' : null;
    }

    public function dehydrate(): void
    {
        if ($this->wallet === null) {
            return;
        }

        $cacheKey = UpdateSecuritiesJob::cacheKeyFor((string) $this->wallet->id);
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

        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));
        $this->shownSecurityIds = array_values(array_diff($allIds, $this->hiddenSecurityIds));
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
        if (in_array($id, $this->hiddenSecurityIds)) {
            $this->hiddenSecurityIds = array_values(array_diff($this->hiddenSecurityIds, [$id]));
        } else {
            $this->hiddenSecurityIds[] = $id;
        }

        $this->computeSecurityVisibility();
        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(static::getNavigationLabel().' '.$this->getFormattedValuation());
    }

    protected function getTotalValuation(): float
    {
        $query = $this->scopedSecuritiesQuery();

        if ($this->shownSecurityIds) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        return (float) $query->with('latestPrice')->get()->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });
    }

    protected function computeAnnualizedReturn(): float
    {
        if ($this->wallet === null) {
            return 7.0;
        }

        $records = $this->scopedSecuritiesQuery()->with('latestPrice')->get();

        $valuation = (float) $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        $totalInvested = (float) $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        if ($totalInvested <= 0 || $valuation <= 0) {
            return 7.0;
        }

        $firstDate = Transaction::query()
            ->where('wallet_id', $this->wallet->id)
            ->where('type', 'buy')
            ->min('date');

        if ($firstDate === null) {
            return 7.0;
        }

        $years = Carbon::parse($firstDate)->diffInDays(now()) / 365.25;

        if ($years < 0.5) {
            return 7.0;
        }

        $cagr = (($valuation / $totalInvested) ** (1 / $years) - 1) * 100;

        return round((float) max(0, min(50, $cagr)), 2);
    }

    protected function computePortfolioVolatility(): float
    {
        if ($this->wallet === null) {
            return 15.0;
        }

        $records = $this->scopedSecuritiesQuery()->with('latestPrice')->get();

        $totalValuation = (float) $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        if ($totalValuation <= 0) {
            return 15.0;
        }

        $weightedVolatility = 0.0;

        foreach ($records as $record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null || (float) $record->total_quantity <= 0) {
                continue;
            }

            $weight = ((float) $record->total_quantity * (float) $close) / $totalValuation;

            $prices = SecurityPrice::query()
                ->where('security_id', $record->id)
                ->orderBy('date')
                ->pluck('close')
                ->map(fn ($v) => (float) $v)
                ->values();

            $sigma = $this->annualizedVolatility($prices);

            if ($sigma === null) {
                continue;
            }

            $weightedVolatility += $weight * $sigma;
        }

        return $weightedVolatility > 0
            ? round($weightedVolatility, 2)
            : 15.0;
    }

    /** @param Collection<int, float> $prices */
    private function annualizedVolatility(Collection $prices): ?float
    {
        if ($prices->count() < 30) {
            return null;
        }

        $returns = [];

        for ($i = 1; $i < $prices->count(); $i++) {
            $prev = $prices[$i - 1];

            if ($prev == 0.0) {
                continue;
            }

            $returns[] = ($prices[$i] - $prev) / $prev;
        }

        $n = count($returns);

        if ($n < 2) {
            return null;
        }

        $mean = array_sum($returns) / $n;
        $variance = array_sum(array_map(fn (float $r) => ($r - $mean) ** 2, $returns)) / ($n - 1);

        return sqrt($variance) * sqrt(252) * 100;
    }

    protected function getFormattedValuation(): string
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

    public function hasMoreRecords(): bool
    {
        if ($this->wallet === null) {
            return false;
        }

        $totalCount = Transaction::query()
            ->where('wallet_id', $this->wallet->id)
            ->distinct('security_id')
            ->count('security_id');

        return $totalCount > $this->tableRecordLimit;
    }

    public function loadMoreAction(): Action
    {
        return Action::make('loadMore')
            ->label('Charger plus')
            ->action(function (): void {
                $this->tableRecordLimit += 2;
                $this->resetTable();
            });
    }

    public function table(Table $table): Table
    {
        return SecuritiesTable::configure(
            $table
                ->query(fn (): Builder => $this->wallet
                    ? Security::query()->forWallet($this->wallet)->limit($this->tableRecordLimit)
                    : Security::query()->forAuth()->whereRaw('0 = 1'))
                ->paginated(false)
                ->recordUrl(fn (Security $record): string => WalletSecurityResource::getUrl('edit', [
                    'record' => $record,
                    'walletId' => $this->wallet?->id,
                ]))
        );
    }

    /**
     * @return list<\Filament\Schemas\Components\Component>
     */
    protected function getExtraContentComponents(): array
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
                    'walletId' => $this->wallet?->id,
                ])->key('performance-stats-overview'),
                Livewire::make(GainStatsOverview::class, [
                    'tablePageClass' => static::class,
                    'shownSecurityIds' => $this->shownSecurityIds,
                    'walletId' => $this->wallet?->id,
                ])->key('gain-stats-overview'),
                Livewire::make(ValuationChartWidget::class, [
                    'tablePageClass' => static::class,
                    'shownSecurityIds' => $this->shownSecurityIds,
                    'walletId' => $this->wallet?->id,
                ])->key('valuation-chart'),
                Section::make('Diversification')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->id('diversification')
                    ->schema([
                        Livewire::make(AllocationChartWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                            'walletId' => $this->wallet?->id,
                        ])->key('allocation-chart'),
                        Livewire::make(SectorAllocationChartWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                            'walletId' => $this->wallet?->id,
                        ])->key('sector-allocation-chart'),
                    ]),
                ...$this->getExtraContentComponents(),
            ]);
    }
}
