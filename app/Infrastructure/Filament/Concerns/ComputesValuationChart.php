<?php

namespace App\Infrastructure\Filament\Concerns;

use Filament\Support\RawJs;

trait ComputesValuationChart
{
    protected function getChartOptions(): RawJs
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
                            callback: (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value),
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

    protected function buildChartDatasets(array $valuations, array $invested, array $fees, array $labels): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Valorisation',
                    'data' => $valuations,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Investi',
                    'data' => $invested,
                    'borderColor' => 'rgb(156, 163, 175)',
                    'backgroundColor' => 'rgba(156, 163, 175, 0.1)',
                    'fill' => false,
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Frais',
                    'data' => $fees,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => false,
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'hidden' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
