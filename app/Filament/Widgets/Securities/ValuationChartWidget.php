<?php

namespace App\Filament\Widgets\Securities;

use App\Models\SecurityPrice;
use App\Models\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ValuationChartWidget extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Evolution de la valorisation';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
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

    private function resolveData(): array
    {
        $securityIds = $this->getPageTableQuery()
            ->reorder()
            ->pluck('securities.id')
            ->toArray();

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

        [$cumulativeQuantities, $cumulativeInvested] = $this->buildCumulatives($transactions);

        $firstTransactionDate = $transactions->first()->date;

        $prices = SecurityPrice::query()
            ->whereIn('security_id', $securityIds)
            ->where('date', '>=', $firstTransactionDate)
            ->orderBy('date')
            ->get(['security_id', 'date', 'close']);

        if ($prices->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        [$labels, $valuations, $invested] = $this->computeValuations($prices, $cumulativeQuantities, $cumulativeInvested, $securityIds);

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
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ' : ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed.y),
                        },
                    },
                },
            }
        JS);
    }

    /**
     * @return array{0: array<int, list<array{date: string, quantity: float}>>, 1: list<array{date: string, invested: float}>}
     */
    private function buildCumulatives(Collection $transactions): array
    {
        $quantities = [];
        $invested = [];
        $totalInvested = 0;

        foreach ($transactions as $transaction) {
            $date = $transaction->date->format('Y-m-d');
            $securityId = $transaction->security_id;

            if (! isset($quantities[$securityId])) {
                $quantities[$securityId] = [];
            }

            $previous = end($quantities[$securityId]) ?: ['quantity' => 0];
            $quantities[$securityId][] = [
                'date' => $date,
                'quantity' => $previous['quantity'] + (float) $transaction->quantity,
            ];

            $totalInvested += (float) $transaction->quantity * (float) $transaction->unit_price + (float) $transaction->fees;
            $invested[] = [
                'date' => $date,
                'invested' => $totalInvested,
            ];
        }

        return [$quantities, $invested];
    }

    /**
     * @return array{0: list<string>, 1: list<float>, 2: list<float>}
     */
    private function computeValuations(Collection $prices, array $cumulativeQuantities, array $cumulativeInvested, array $securityIds): array
    {
        $weeklyPrices = $prices
            ->groupBy(fn ($price) => $price->security_id.'-'.Carbon::parse($price->date)->startOfWeek()->format('Y-m-d'))
            ->map(fn (Collection $group) => $group->last());

        $weeks = $weeklyPrices
            ->map(fn ($price) => Carbon::parse($price->date)->startOfWeek()->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $pricesByWeekAndSecurity = $weeklyPrices->groupBy(
            fn ($p) => Carbon::parse($p->date)->startOfWeek()->format('Y-m-d'),
        )->map(fn (Collection $group) => $group->keyBy('security_id'));

        $labels = [];
        $valuations = [];
        $invested = [];

        foreach ($weeks as $week) {
            $valuation = 0;
            $pricesForWeek = $pricesByWeekAndSecurity->get($week, collect());

            foreach ($securityIds as $securityId) {
                $price = $pricesForWeek->get($securityId);
                if (! $price) {
                    continue;
                }

                $quantity = $this->getQuantityAtDate($cumulativeQuantities, $securityId, Carbon::parse($price->date)->format('Y-m-d'));
                $valuation += $quantity * (float) $price->close;
            }

            $labels[] = Carbon::parse($week)->format('d/m/Y');
            $valuations[] = round($valuation, 2);
            $invested[] = round($this->getInvestedAtDate($cumulativeInvested, $week), 2);
        }

        return [$labels, $valuations, $invested];
    }

    private function getQuantityAtDate(array $cumulativeQuantities, int $securityId, string $date): float
    {
        if (! isset($cumulativeQuantities[$securityId])) {
            return 0;
        }

        $quantity = 0;
        foreach ($cumulativeQuantities[$securityId] as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $quantity = $entry['quantity'];
        }

        return $quantity;
    }

    private function getInvestedAtDate(array $cumulativeInvested, string $date): float
    {
        $invested = 0;
        foreach ($cumulativeInvested as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $invested = $entry['invested'];
        }

        return $invested;
    }
}
