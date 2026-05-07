<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Portfolio\Data\CumulativeData;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Services\TransactionAggregator;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use App\Infrastructure\Filament\Widgets\ChartWidget;
use App\Infrastructure\Support\ChartColors;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ValuationChartWidget extends ChartWidget
{
    use HasFiltersSchema;
    use HasReactiveTableProperties;

    protected ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.flat-chart-widget';

    protected ?string $maxHeight = '200px';

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
        $securities = $this->getFilteredSecurities(withPrice: false, reorder: true);
        $securityIds = $securities->pluck('id')->toArray();

        if (empty($securityIds)) {
            return ['datasets' => [], 'labels' => []];
        }

        $transactions = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date')
            ->get();

        if ($transactions->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $aggregator = app(TransactionAggregator::class);

        $cumulative = $aggregator->buildCumulatives($transactions);

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
                $aggregator,
                $prices,
                $cumulative,
                $securityIds,
            );

            return $this->buildStackedAreaDatasets($valuationsBySecurity, $invested, $fees, $labels, $securityNames, $securityIds);
        }

        $result = $aggregator->computeDailyValuations($prices, $cumulative, $securityIds);

        return $this->buildTotalDatasets($result->valuations, $result->invested, $result->fees, $result->labels);
    }

    /**
     * @return array{0: list<string>, 1: array<int, list<float>>, 2: list<float>, 3: list<float>}
     */
    private function computeValuationsPerSecurity(
        TransactionAggregator $aggregator,
        Collection $prices,
        CumulativeData $cumulative,
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
        $lastCloseBySecurityId = [];

        foreach ($securityIds as $securityId) {
            $valuationsBySecurity[$securityId] = [];
        }

        foreach ($days as $day) {
            $pricesForDay = $pricesByDayAndSecurity->get($day, collect());

            foreach ($securityIds as $securityId) {
                $price = $pricesForDay->get($securityId);
                if ($price) {
                    $lastCloseBySecurityId[$securityId] = (float) $price->close;
                }

                $close = $lastCloseBySecurityId[$securityId] ?? null;
                $quantity = $aggregator->getQuantityAtDate($cumulative->quantities, $securityId, $day);
                $value = $close !== null ? round($quantity * $close, 2) : 0;
                $valuationsBySecurity[$securityId][] = $value;
            }

            $labels[] = $day;
            $invested[] = round($aggregator->getValueAtDate($cumulative->invested, $day), 2);
            $fees[] = round($aggregator->getValueAtDate($cumulative->fees, $day), 2);
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

            $color = ChartColors::withAlpha($colorIndex);

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
                    legend: { display: false },
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
