<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Infrastructure\Filament\Widgets\ChartWidget;
use Filament\Support\RawJs;

class PortfolioAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Poids des portefeuilles';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    private const COLORS = [
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(59, 130, 246)',
        'rgb(168, 85, 247)',
    ];

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $provider = app(DashboardDataProvider::class);
        $labels = [];
        $data = [];
        $colors = [];

        foreach ($provider->wallets()->sortBy('id')->values() as $index => $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            $valuation = $securities->sum(fn ($security) => $security->currentValuation());

            $labels[] = $wallet->name;
            $data[] = round($valuation, 2);
            $colors[] = self::COLORS[$index % count(self::COLORS)];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'start',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 8,
                            font: { size: 12 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ' : ' + value + ' (' + percentage + '%)';
                            },
                        },
                    },
                },
            }
        JS);
    }
}
