<?php

namespace App\Filament\Widgets\Securities;

use App\Models\SecurityPrice;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityPriceChartWidget extends ChartWidget
{
    protected ?string $heading = 'Evolution du prix';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public ?Model $record = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $prices = SecurityPrice::query()
            ->where('security_id', $this->record->id)
            ->orderBy('date')
            ->get(['date', 'close']);

        if ($prices->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prix (close)',
                    'data' => $prices->pluck('close')->map(fn ($v) => round((float) $v, 2))->all(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $prices->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 12,
                            maxRotation: 0,
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                const date = new Date(label);
                                const month = date.toLocaleDateString('fr-FR', { month: 'short' });
                                const year = date.toLocaleDateString('fr-FR', { year: '2-digit' });
                                return month + ' ' + year;
                            },
                        },
                    },
                    y: {
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 }).format(value),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const date = new Date(items[0].label);
                                return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
                            },
                            label: (context) => context.dataset.label + ' : ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed.y),
                        },
                    },
                },
            }
        JS);
    }
}
