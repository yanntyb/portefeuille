<?php

namespace App\Infrastructure\Filament\Concerns;

use Filament\Support\RawJs;

trait ComputesValuationChart
{
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
