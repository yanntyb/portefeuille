<?php

namespace App\Filament\Widgets\Securities;

use App\Support\ChartColors;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Livewire\Attributes\On;

class AllocationChartWidget extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Répartition par titre';

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

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

        $query = $this->getPageTableQuery()->reorder();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $securities = $query->with('latestPrice')->get();

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

            $labels[] = $security->name;
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
                        position: 'bottom',
                        align: 'start',
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
