<?php

namespace App\Domains\Portfolio\Filament\Pages;

use App\Domains\Portfolio\Data\AccountPageData;
use App\Domains\Portfolio\Filament\Resources\WalletSecurities\WalletSecurityResource;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Services\PortfolioPerformanceService;
use App\Domains\Security\Filament\Resources\SecurityBase\Tables\SecuritiesTable;
use App\Domains\Security\Filament\Widgets\AllocationChartWidget;
use App\Domains\Security\Filament\Widgets\CorrelationMatrixWidget;
use App\Domains\Security\Filament\Widgets\GainStatsOverview;
use App\Domains\Security\Filament\Widgets\PerformanceStatsOverview;
use App\Domains\Security\Filament\Widgets\SectorAllocationChartWidget;
use App\Domains\Security\Filament\Widgets\ValuationChartWidget;
use App\Domains\Security\Filament\Widgets\ValuationStatOverview;
use App\Domains\Security\Jobs\UpdateSecuritiesJob;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Services\PriceRefreshService;
use App\Infrastructure\Concerns\HasTableStore;
use App\Infrastructure\Contracts\TableStoreable;
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
use Illuminate\Support\Facades\Cache;
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
        if ($this->wallet === null) {
            return;
        }

        $service = app(PortfolioPerformanceService::class);
        $result = $service->computeSecurityVisibility($this->wallet, $this->hiddenSecurityIds);
        $this->shownSecurityIds = $result['shown_ids'];
        $this->pricelessSecurityIds = $result['priceless_ids'];
    }

    public function refreshPrices(): void
    {
        $securityIds = $this->scopedSecuritiesQuery()
            ->pluck('securities.id')
            ->all();

        $securities = Security::query()
            ->whereIn('id', $securityIds)
            ->whereNotNull('ticker')
            ->with('currentPrice')
            ->get();

        app(PriceRefreshService::class)->refreshIfNeeded($securities);

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
        return static::getNavigationLabel();
    }

    protected function getTotalValuation(): float
    {
        if ($this->wallet === null) {
            return 0.0;
        }

        return app(PortfolioPerformanceService::class)->getTotalValuation($this->wallet, $this->shownSecurityIds);
    }

    protected function computeAnnualizedReturn(): float
    {
        return app(PortfolioPerformanceService::class)->computeAnnualizedReturn($this->wallet);
    }

    protected function computePortfolioVolatility(): float
    {
        return app(PortfolioPerformanceService::class)->computePortfolioVolatility($this->wallet);
    }

    protected function getFormattedValuation(): string
    {
        if ($this->wallet === null) {
            return '';
        }

        return app(PortfolioPerformanceService::class)->getFormattedValuation($this->wallet, $this->shownSecurityIds);
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
                Section::make()
                    ->contained(false)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Livewire::make(ValuationStatOverview::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                            'walletId' => $this->wallet?->id,
                        ])->key('valuation-stat-overview')
                            ->columnSpan(1),
                        Livewire::make(ValuationChartWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                            'walletId' => $this->wallet?->id,
                        ])->key('valuation-chart')
                            ->columnSpan(1),
                    ]),
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
                        Livewire::make(CorrelationMatrixWidget::class, [
                            'tablePageClass' => static::class,
                            'shownSecurityIds' => $this->shownSecurityIds,
                            'walletId' => $this->wallet?->id,
                        ])->key('correlation-matrix'),
                    ]),
                ...$this->getExtraContentComponents(),
            ]);
    }
}
