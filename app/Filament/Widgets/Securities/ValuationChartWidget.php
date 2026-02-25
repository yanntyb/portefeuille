<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesValuationChart;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ValuationChartWidget extends ChartWidget
{
    use ComputesValuationChart;
    use HasFiltersSchema;
    use InteractsWithPageTable;

    protected ?string $heading = 'Evolution de la valorisation';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = null;

    private const COLORS = [
        ['border' => 'rgb(59, 130, 246)', 'bg' => 'rgba(59, 130, 246, 0.4)'],
        ['border' => 'rgb(16, 185, 129)', 'bg' => 'rgba(16, 185, 129, 0.4)'],
        ['border' => 'rgb(245, 158, 11)', 'bg' => 'rgba(245, 158, 11, 0.4)'],
        ['border' => 'rgb(239, 68, 68)', 'bg' => 'rgba(239, 68, 68, 0.4)'],
        ['border' => 'rgb(139, 92, 246)', 'bg' => 'rgba(139, 92, 246, 0.4)'],
        ['border' => 'rgb(236, 72, 153)', 'bg' => 'rgba(236, 72, 153, 0.4)'],
        ['border' => 'rgb(20, 184, 166)', 'bg' => 'rgba(20, 184, 166, 0.4)'],
        ['border' => 'rgb(249, 115, 22)', 'bg' => 'rgba(249, 115, 22, 0.4)'],
        ['border' => 'rgb(99, 102, 241)', 'bg' => 'rgba(99, 102, 241, 0.4)'],
        ['border' => 'rgb(34, 197, 94)', 'bg' => 'rgba(34, 197, 94, 0.4)'],
    ];

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('mode')
                ->label('')
                ->options([
                    'total' => 'Total',
                    'per_security' => 'Par titre',
                ])
                ->default('total')
                ->grouped(),
        ]);
    }

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

        return $this->resolveData();
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function isStackedMode(): bool
    {
        return ($this->filters['mode'] ?? 'total') === 'per_security';
    }

    private function resolveData(): array
    {
        $securityIds = $this->getPageTableQuery()
            ->reorder()
            ->pluck('securities.id')
            ->toArray();

        if ($this->shownSecurityIds !== null) {
            $securityIds = array_values(array_intersect($securityIds, $this->shownSecurityIds));
        }

        if (empty($securityIds)) {
            return ['datasets' => [], 'labels' => []];
        }

        $transactions = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date')
            ->get(['security_id', 'date', 'quantity', 'unit_price', 'fees']);

        if ($transactions->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        [$cumulativeQuantities, $cumulativeInvested, $cumulativeFees] = $this->buildCumulatives($transactions);

        $firstTransactionDate = $transactions->first()->date;

        $prices = SecurityPrice::query()
            ->whereIn('security_id', $securityIds)
            ->where('date', '>=', $firstTransactionDate)
            ->orderBy('date')
            ->get(['security_id', 'date', 'close']);

        if ($prices->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        if ($this->isStackedMode()) {
            $securityNames = Security::query()
                ->whereIn('id', $securityIds)
                ->pluck('name', 'id');

            [$labels, $valuationsBySecurity, $invested, $fees] = $this->computeValuationsPerSecurity(
                $prices,
                $cumulativeQuantities,
                $cumulativeInvested,
                $cumulativeFees,
                $securityIds,
            );

            return $this->buildStackedAreaDatasets($valuationsBySecurity, $invested, $fees, $labels, $securityNames, $securityIds);
        }

        [$labels, $valuations, $invested, $fees] = $this->computeValuations(
            $prices,
            $cumulativeQuantities,
            $cumulativeInvested,
            $cumulativeFees,
            $securityIds,
        );

        return $this->buildTotalDatasets($valuations, $invested, $fees, $labels);
    }

    /**
     * @return array{0: list<string>, 1: array<int, list<float>>, 2: list<float>, 3: list<float>}
     */
    private function computeValuationsPerSecurity(
        Collection $prices,
        array $cumulativeQuantities,
        array $cumulativeInvested,
        array $cumulativeFees,
        array $securityIds,
    ): array {
        $days = $prices
            ->map(fn ($price) => Carbon::parse($price->date)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $pricesByDayAndSecurity = $prices->groupBy(
            fn ($p) => Carbon::parse($p->date)->format('Y-m-d'),
        )->map(fn (Collection $group) => $group->keyBy('security_id'));

        $labels = [];
        $valuationsBySecurity = [];
        $invested = [];
        $fees = [];

        foreach ($securityIds as $securityId) {
            $valuationsBySecurity[$securityId] = [];
        }

        foreach ($days as $day) {
            $pricesForDay = $pricesByDayAndSecurity->get($day, collect());

            foreach ($securityIds as $securityId) {
                $price = $pricesForDay->get($securityId);
                $quantity = $this->getQuantityAtDate($cumulativeQuantities, $securityId, $day);
                $value = $price ? round($quantity * (float) $price->close, 2) : 0;
                $valuationsBySecurity[$securityId][] = $value;
            }

            $labels[] = $day;
            $invested[] = round($this->getInvestedAtDate($cumulativeInvested, $day), 2);
            $fees[] = round($this->getFeesAtDate($cumulativeFees, $day), 2);
        }

        return [$labels, $valuationsBySecurity, $invested, $fees];
    }

    private function buildTotalDatasets(array $valuations, array $invested, array $fees, array $labels): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Valorisation',
                    'data' => $valuations,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => false,
                    'stack' => 'total',
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Investi',
                    'data' => $invested,
                    'borderColor' => 'rgb(156, 163, 175)',
                    'backgroundColor' => 'rgba(156, 163, 175, 0.1)',
                    'fill' => false,
                    'stack' => 'overlay',
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Frais',
                    'data' => $fees,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => false,
                    'stack' => 'overlay',
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'hidden' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  array<int, list<float>>  $valuationsBySecurity
     * @param  Collection<int, string>  $securityNames
     * @param  list<int>  $securityIds
     */
    private function buildStackedAreaDatasets(
        array $valuationsBySecurity,
        array $invested,
        array $fees,
        array $labels,
        Collection $securityNames,
        array $securityIds,
    ): array {
        $datasets = [];
        $colorIndex = 0;

        foreach ($securityIds as $securityId) {
            $data = $valuationsBySecurity[$securityId] ?? [];

            if (array_sum($data) <= 0) {
                continue;
            }

            $color = self::COLORS[$colorIndex % count(self::COLORS)];

            $datasets[] = [
                'label' => $securityNames->get($securityId, "Titre #{$securityId}"),
                'data' => $data,
                'borderColor' => $color['border'],
                'backgroundColor' => $color['bg'],
                'fill' => true,
                'stack' => 'valuation',
                'tension' => 0.3,
                'pointRadius' => 0,
            ];
            $colorIndex++;
        }

        $datasets[] = [
            'label' => 'Investi',
            'data' => $invested,
            'borderColor' => 'rgb(156, 163, 175)',
            'fill' => false,
            'stack' => 'overlay',
            'borderDash' => [5, 5],
            'tension' => 0.3,
            'pointRadius' => 0,
        ];

        $datasets[] = [
            'label' => 'Frais',
            'data' => $fees,
            'borderColor' => 'rgb(239, 68, 68)',
            'fill' => false,
            'stack' => 'overlay',
            'borderDash' => [5, 5],
            'tension' => 0.3,
            'pointRadius' => 0,
            'hidden' => true,
        ];

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
                        stacked: true,
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
                        stacked: true,
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
}
