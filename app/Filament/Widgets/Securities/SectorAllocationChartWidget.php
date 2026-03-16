<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\ChartWidget;
use App\Filament\Widgets\Securities\Concerns\HasReactiveTableProperties;
use App\Models\SecuritySector;
use App\Services\SectorAggregator;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class SectorAllocationChartWidget extends ChartWidget
{
    use HasReactiveTableProperties;

    protected ?string $heading = 'Répartition sectorielle';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    private ?Builder $cachedPageTableQuery = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    public ?Model $record = null;

    private function getCachedPageTableQuery(): Builder
    {
        return $this->cachedPageTableQuery ??= $this->getPageTableQuery()->reorder();
    }

    public function getDescription(): ?string
    {
        return null;
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

        return app(SectorAggregator::class)->buildStackedSectorData($securities);
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
                                autoSkip: false,
                                crossAlign: 'far',
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
                            autoSkip: false,
                            crossAlign: 'far',
                        },
                    },
                },
            }
        JS);
    }
}
