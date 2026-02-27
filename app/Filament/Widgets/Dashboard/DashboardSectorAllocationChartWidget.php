<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Sector;
use App\Services\DashboardDataProvider;
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

        /** @var array<string, array<string, float>> */
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
            $this->maxHeight = '300px';

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

        $labelCount = count($labels);
        $this->maxHeight = ($labelCount * 40 + 60).'px';

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
