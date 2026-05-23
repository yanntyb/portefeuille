<?php

namespace App\Infrastructure\Filament\Widgets;

use App\Domains\Asset\Services\AssetPriceAggregator;
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class AssetPriceChartWidget extends ChartWidget
{
    #[Locked]
    public int $assetId;

    public string $period = '1Y';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.asset-price-chart-widget';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(156, 163, 175, 0.15)'],
                    'ticks' => ['maxTicksLimit' => 6],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxTicksLimit' => 8],
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        [$from, $to] = $this->resolveDateRange();

        $priceViewPort = app(AssetPriceViewPort::class);
        $history = $priceViewPort->getPriceHistory($this->assetId, $from, $to);

        if ($history->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $aggregator = app(AssetPriceAggregator::class);
        $aggregated = $aggregator->aggregateByWeek($history);

        $dates = $aggregated->pluck('date')->toArray();
        $closes = $aggregated->pluck('close')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Clôture (moyenne hebdomadaire)',
                    'data' => $closes,
                    'borderColor' => 'rgb(52, 211, 153)',
                    'backgroundColor' => 'rgba(52, 211, 153, 0.15)',
                    'tension' => 0.3,
                    'fill' => 'origin',
                ],
            ],
            'labels' => $dates,
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->cachedData = null;
    }

    public function setCustomPeriod(): void
    {
        $this->period = 'custom';
        $this->cachedData = null;
    }

    private function resolveDateRange(): array
    {
        $to = today();

        if ($this->period === 'custom') {
            return [
                $this->dateFrom ? Carbon::parse($this->dateFrom) : today()->subYear(),
                $this->dateTo ? Carbon::parse($this->dateTo) : $to,
            ];
        }

        return match ($this->period) {
            '1M' => [today()->subDays(30), $to],
            '3M' => [today()->subMonths(3), $to],
            '6M' => [today()->subMonths(6), $to],
            default => [today()->subYear(), $to],
        };
    }
}
