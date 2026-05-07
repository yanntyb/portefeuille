<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use App\Infrastructure\Filament\Widgets\ChartWidget;
use App\Infrastructure\Support\ChartColors;
use Filament\Support\RawJs;
use Livewire\Attributes\On;

class AllocationChartWidget extends ChartWidget
{
    use HasReactiveTableProperties;

    protected ?string $heading = 'Répartition par titre';

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }

    #[On('prices-updated')]
    public function refreshChart(): void
    {
        $this->updateChartData();
    }

    protected function getData(): array
    {
        if ($this->tablePageClass === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $securities = $this->getFilteredSecurities(withPrice: true, reorder: true);

        $labels = [];
        $valuations = [];
        $colors = [];
        $colorIndex = 0;

        foreach ($securities as $security) {
            $quantity = (float) $security->total_quantity;
            $price = $security->latestPrice?->close;

            if ($quantity <= 0 || $price === null) {
                continue;
            }

            $name = $security->name;
            $labels[] = mb_strlen($name) > 20 ? mb_substr($name, 0, 20).'…' : $name;
            $valuations[] = $quantity * (float) $price;
            $colors[] = ChartColors::at($colorIndex);
            $colorIndex++;
        }

        $total = array_sum($valuations);
        $data = $total > 0
            ? array_map(fn (float $v): float => round(($v / $total) * 100, 1), $valuations)
            : [];

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
                        position: 'left',
                        align: 'center',
                        labels: {
                            padding: 8,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return context.label + ' : ' + context.parsed + ' %';
                            },
                        },
                    },
                },
            }
        JS);
    }
}
