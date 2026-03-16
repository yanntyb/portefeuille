<?php

namespace App\Filament\Widgets\Simulation;

use App\Filament\Widgets\ChartWidget;
use App\Services\MonteCarloEngine;
use Filament\Support\RawJs;
use Livewire\Attributes\On;

class MonteCarloChartWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.bare-chart-widget';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = null;

    public float $capitalInitial = 10000;

    public float $versementMensuel = 500;

    public float $tauxMoyen = 7;

    public float $volatilite = 15;

    public int $duree = 20;

    public int $nbSimulations = 500;

    public function getHeading(): ?string
    {
        return null;
    }

    #[On('simulation-settings-updated')]
    public function onSettingsUpdated(
        float $versementMensuel,
        float $tauxMoyen,
        float $volatilite,
        int $nbSimulations,
    ): void {
        $this->versementMensuel = $versementMensuel;
        $this->tauxMoyen = $tauxMoyen;
        $this->volatilite = $volatilite;
        $this->nbSimulations = $nbSimulations;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $result = app(MonteCarloEngine::class)->compute(
            capitalInitial: $this->capitalInitial,
            versementMensuel: $this->versementMensuel,
            tauxMoyen: $this->tauxMoyen / 100,
            volatilite: $this->volatilite / 100,
            duree: $this->duree,
            nbSimulations: $this->nbSimulations,
        );

        $labels = array_map(fn (int $y): string => "Année {$y}", range(0, $result->duree));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'P90 (optimiste)',
                    'data' => array_values($result->p90),
                    'borderColor' => '#22c55e',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'P50 (médiane)',
                    'data' => array_values($result->p50),
                    'borderColor' => '#3b82f6',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'P10 (pessimiste)',
                    'data' => array_values($result->p10),
                    'borderColor' => '#ef4444',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Capital investi',
                    'data' => array_values($result->capitalInvesti),
                    'borderColor' => '#94a3b8',
                    'fill' => false,
                    'tension' => 0,
                    'pointRadius' => 0,
                    'borderDash' => [6, 4],
                ],
            ],
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
                y: {
                    ticks: {
                        callback: (v) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v),
                    },
                },
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.label + ' : ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(ctx.parsed.y),
                    },
                },
            },
        }
        JS);
    }
}
