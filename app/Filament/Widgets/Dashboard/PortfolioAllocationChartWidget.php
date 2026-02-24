<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\AccountType;
use App\Services\DashboardDataProvider;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class PortfolioAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Répartition par compte';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    private const COLORS = [
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
    ];

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $provider = app(DashboardDataProvider::class);
        $accountTypes = [AccountType::Pea, AccountType::Cto];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($accountTypes as $index => $accountType) {
            $securities = $provider->securitiesForAccount($accountType);

            $valuation = $securities->sum(function ($security) {
                $close = $security->latestPrice?->close;

                if ($close === null || $security->total_quantity === null) {
                    return 0;
                }

                return (float) $security->total_quantity * (float) $close;
            });

            $labels[] = $accountType->getLabel();
            $data[] = round($valuation, 2);
            $colors[] = self::COLORS[$index];
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
