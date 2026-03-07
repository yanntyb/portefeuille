<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\DashboardDataProvider;
use App\Services\SectorAggregator;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class DashboardSectorAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Répartition sectorielle';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.scrollable-chart-widget';

    public function getDescription(): ?string
    {
        $securities = app(DashboardDataProvider::class)->allSecurities();
        $securityIds = $securities->pluck('id');

        if ($securityIds->isEmpty()) {
            return null;
        }

        $date = \App\Models\SecuritySector::query()
            ->whereIn('security_id', $securityIds)
            ->min('updated_at');

        if ($date === null) {
            return null;
        }

        return 'Maj le : '.Carbon::parse($date)->translatedFormat('d M Y à H\hi');
    }

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $securities = app(DashboardDataProvider::class)->allSecurities()->load('sectors');

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
