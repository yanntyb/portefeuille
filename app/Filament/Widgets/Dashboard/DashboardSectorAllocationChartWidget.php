<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Domains\Portfolio\Services\SectorAggregator;
use App\Filament\Widgets\ChartWidget;
use Filament\Support\RawJs;
use Livewire\Attributes\On;

class DashboardSectorAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Répartition sectorielle';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
        $this->updateChartData();
    }

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $securities = app(DashboardDataProvider::class)->allSecurities()->load('sectors');

        if ($this->shownSecurityIds !== null) {
            $securities = $securities->whereIn('id', $this->shownSecurityIds);
        }

        $data = app(SectorAggregator::class)->buildStackedSectorData($securities);

        $labelCount = count($data['labels']);
        $this->maxHeight = $labelCount > 0
            ? ($labelCount * 40 + 60).'px'
            : '300px';

        return $data;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
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
