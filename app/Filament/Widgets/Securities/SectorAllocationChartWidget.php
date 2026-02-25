<?php

namespace App\Filament\Widgets\Securities;

use App\Enums\Sector;
use App\Models\SecuritySector;
use Filament\Support\RawJs;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

use function Livewire\trigger;

class SectorAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Répartition sectorielle';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    private ?Builder $cachedPageTableQuery = null;

    private ?HasTable $tablePage = null;

    private const COLORS = [
        'rgb(59, 130, 246)',
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(239, 68, 68)',
        'rgb(139, 92, 246)',
        'rgb(236, 72, 153)',
        'rgb(20, 184, 166)',
        'rgb(249, 115, 22)',
        'rgb(99, 102, 241)',
        'rgb(34, 197, 94)',
    ];

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    public ?Model $record = null;

    /** @var array<string, int> */
    #[Reactive]
    public ?array $paginators = [];

    /** @var array<string, string | array<string, string | null> | null> */
    #[Reactive]
    public ?array $tableColumnSearches = [];

    /** @var array<string, mixed> | null */
    #[Reactive]
    public ?array $tableFilters = null;

    #[Reactive]
    public ?string $tableSearch = '';

    #[Reactive]
    public ?string $tableSort = null;

    #[Reactive]
    public ?string $tableGrouping = null;

    #[Reactive]
    public int|string|null $tableRecordsPerPage = null;

    #[Reactive]
    public ?string $activeTab = null;

    #[Reactive]
    public ?int $tableRecordsCount = null;

    #[Reactive]
    public ?Model $parentRecord = null;

    private function getTablePageInstance(): HasTable
    {
        if ($this->tablePage !== null) {
            return $this->tablePage;
        }

        /** @var HasTable $page */
        $page = app('livewire')->new($this->tablePageClass);

        trigger('mount', $page, [], null, null, []);

        foreach ([
            'activeTab' => $this->activeTab,
            'paginators' => $this->paginators ?? [],
            'parentRecord' => $this->parentRecord,
            'tableColumnSearches' => $this->tableColumnSearches ?? [],
            'tableFilters' => $this->tableFilters,
            'tableGrouping' => $this->tableGrouping,
            'tableRecordsPerPage' => $this->tableRecordsPerPage,
            'tableSearch' => $this->tableSearch,
            'tableSort' => $this->tableSort,
        ] as $property => $value) {
            $page->{$property} = $value;
        }

        $page->bootedInteractsWithTable();

        return $this->tablePage = $page;
    }

    private function getPageTableQuery(): Builder
    {
        return $this->getTablePageInstance()->getFilteredSortedTableQuery();
    }

    private function getCachedPageTableQuery(): Builder
    {
        return $this->cachedPageTableQuery ??= $this->getPageTableQuery()->reorder();
    }

    public function getDescription(): ?string
    {
        $oldestUpdatedAt = $this->getOldestSectorUpdate();

        if ($oldestUpdatedAt === null) {
            return null;
        }

        return 'Maj le : '.$oldestUpdatedAt->translatedFormat('d M Y à H\hi');
    }

    private function getOldestSectorUpdate(): ?Carbon
    {
        if ($this->record !== null) {
            $date = $this->record->sectors()->min('updated_at');

            return $date ? Carbon::parse($date) : null;
        }

        if ($this->tablePageClass !== null) {
            $securityIds = $this->getCachedPageTableQuery()->clone()->pluck('securities.id');

            if ($securityIds->isEmpty()) {
                return null;
            }

            $date = SecuritySector::query()
                ->whereIn('security_id', $securityIds)
                ->min('updated_at');

            return $date ? Carbon::parse($date) : null;
        }

        return null;
    }

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        if ($this->record !== null) {
            $data = $this->getDataForSingleSecurity();
        } elseif ($this->tablePageClass !== null) {
            $data = $this->getDataForAccountList();
        } else {
            $data = ['datasets' => [], 'labels' => []];
        }

        $labelCount = count($data['labels'] ?? []);
        $this->maxHeight = $labelCount > 0
            ? ($labelCount * 40 + 60).'px'
            : '300px';

        return $data;
    }

    private function getDataForSingleSecurity(): array
    {
        $this->record->loadMissing('sectors');
        $sectors = $this->record->sectors;

        if ($sectors->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $items = $sectors->map(fn ($s) => [
            'label' => $s->sector->getLabel(),
            'value' => round((float) $s->weight * 100, 2),
        ])->sortByDesc('value')->values();

        return [
            'datasets' => [
                [
                    'data' => $items->pluck('value')->all(),
                    'backgroundColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $items->pluck('label')->all(),
        ];
    }

    private function getDataForAccountList(): array
    {
        $query = $this->getCachedPageTableQuery()->clone();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $securities = $query->with(['latestPrice', 'sectors'])->get();

        /** @var array<string, array<string, float>> sector_value => [security_name => amount] */
        $sectorBySecurity = [];
        $sectorTotals = [];
        $securityNames = [];
        $colorIndex = 0;

        foreach ($securities as $security) {
            $quantity = (float) $security->total_quantity;
            $price = $security->latestPrice?->close;

            if ($quantity <= 0 || $price === null) {
                continue;
            }

            $valuation = $quantity * (float) $price;
            $securityNames[$security->id] = $security->name;

            foreach ($security->sectors as $sectorRecord) {
                $key = $sectorRecord->sector->value;
                $amount = $valuation * (float) $sectorRecord->weight;
                $sectorBySecurity[$key][$security->id] = ($sectorBySecurity[$key][$security->id] ?? 0) + $amount;
                $sectorTotals[$key] = ($sectorTotals[$key] ?? 0) + $amount;
            }
        }

        if ($sectorTotals === []) {
            return ['datasets' => [], 'labels' => []];
        }

        $grandTotal = array_sum($sectorTotals);

        arsort($sectorTotals);
        $sortedSectorKeys = array_keys($sectorTotals);

        $labels = array_map(fn ($key) => Sector::from($key)->getLabel(), $sortedSectorKeys);

        $datasets = [];
        $securityIds = array_keys($securityNames);

        foreach ($securityIds as $securityId) {
            $data = [];
            foreach ($sortedSectorKeys as $sectorKey) {
                $amount = $sectorBySecurity[$sectorKey][$securityId] ?? 0;
                $data[] = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 1) : 0;
            }

            $datasets[] = [
                'label' => $securityNames[$securityId],
                'data' => $data,
                'backgroundColor' => self::COLORS[$colorIndex % count(self::COLORS)],
                'borderWidth' => 0,
            ];
            $colorIndex++;
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        $isPercentMode = $this->record !== null;

        if ($isPercentMode) {
            return RawJs::make(<<<'JS'
                {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.parsed.x.toFixed(1) + ' %',
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: (value) => value + ' %',
                            },
                        },
                        y: {
                            ticks: {
                                crossAlign: 'far',
                                callback: function(value) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 18 ? label.substring(0, 17) + '…' : label;
                                },
                            },
                        },
                    },
                }
            JS);
        }

        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return context.dataset.label + ' : ' + context.parsed.x + ' %';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            callback: (value) => value + ' %',
                        },
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            crossAlign: 'far',
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                return label.length > 18 ? label.substring(0, 17) + '…' : label;
                            },
                        },
                    },
                },
            }
        JS);
    }
}
