<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Widgets\ChartWidget;
use App\Models\Wallet;
use App\Services\DashboardDataProvider;
use Filament\Support\RawJs;
use Livewire\Attributes\On;

class PortfolioAllocationChartWidget extends ChartWidget
{
    protected ?string $heading = 'Répartition par compte';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    private const COLORS = [
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(59, 130, 246)',
        'rgb(168, 85, 247)',
    ];

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
        $this->updateChartData();
    }

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $provider = app(DashboardDataProvider::class);
        $wallets = Wallet::withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->orderBy('id')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($wallets as $index => $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $valuation = $securities->sum(function ($security) {
                $close = $security->latestPrice?->close;

                if ($close === null || $security->total_quantity === null) {
                    return 0;
                }

                return (float) $security->total_quantity * (float) $close;
            });

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
