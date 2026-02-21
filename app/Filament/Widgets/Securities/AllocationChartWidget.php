<?php

namespace App\Filament\Widgets\Securities;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Livewire\Attributes\On;

class AllocationChartWidget extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Répartition par titre';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    private const COLORS = [
        'rgb(59, 130, 246)',
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(239, 68, 68)',
        'rgb(139, 92, 246)',
        'rgb(236, 72, 153)',
        'rgb(20, 184, 166)',
        'rgb(249, 115, 22)',
        'rgb(99, 102, 241)',
        'rgb(34, 197, 94)',
    ];

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
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
        $data = [];
        $colors = [];
        $colorIndex = 0;

        foreach ($securities as $security) {
            $quantity = (float) $security->total_quantity;
            $price = $security->latestPrice?->close;

            if ($quantity <= 0 || $price === null) {
                continue;
            }

            $labels[] = $security->name;
            $data[] = round($quantity * (float) $price, 2);
            $colors[] = self::COLORS[$colorIndex % count(self::COLORS)];
            $colorIndex++;
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
