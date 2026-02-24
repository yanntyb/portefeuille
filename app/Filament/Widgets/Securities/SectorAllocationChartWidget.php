<?php

namespace App\Filament\Widgets\Securities;

use App\Enums\Sector;
use App\Models\SecuritySector;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class SectorAllocationChartWidget extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Répartition sectorielle';

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

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

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
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
            $securityIds = $this->getPageTableQuery()->reorder()->pluck('securities.id');

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
            return $this->getDataForSingleSecurity();
        }

        if ($this->tablePageClass !== null) {
            return $this->getDataForAccountList();
        }

        return ['datasets' => [], 'labels' => []];
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
        $query = $this->getPageTableQuery()->reorder();

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

        arsort($sectorTotals);
        $sortedSectorKeys = array_keys($sectorTotals);

        $labels = array_map(fn ($key) => Sector::from($key)->getLabel(), $sortedSectorKeys);

        $datasets = [];
        $securityIds = array_keys($securityNames);

        foreach ($securityIds as $securityId) {
            $data = [];
            foreach ($sortedSectorKeys as $sectorKey) {
                $data[] = round($sectorBySecurity[$sectorKey][$securityId] ?? 0, 2);
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
                                const value = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed.x);
                                const total = context.chart.data.datasets.reduce((sum, ds) => sum + (ds.data[context.dataIndex] || 0), 0);
                                const percentage = total > 0 ? ((context.parsed.x / total) * 100).toFixed(1) : '0.0';
                                return context.dataset.label + ' : ' + value + ' (' + percentage + '%)';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value),
                        },
                    },
                    y: {
                        stacked: true,
                    },
                },
            }
        JS);
    }
}
