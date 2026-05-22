<?php

namespace App\Infrastructure\Filament\Widgets;

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

    protected static string $view = 'filament.widgets.asset-price-chart-widget';

    protected function getType(): string
    {
        return 'line';
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

        $dates = $history->pluck('date')->toArray();
        $closes = $history->pluck('close')->toArray();
        $opens = $history->pluck('open')->toArray();
        $highs = $history->pluck('high')->toArray();
        $lows = $history->pluck('low')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Clôture',
                    'data' => $closes,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'Ouverture',
                    'data' => $opens,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                    'hidden' => true,
                ],
                [
                    'label' => 'Plus haut',
                    'data' => $highs,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                    'hidden' => true,
                ],
                [
                    'label' => 'Plus bas',
                    'data' => $lows,
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                    'hidden' => true,
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
