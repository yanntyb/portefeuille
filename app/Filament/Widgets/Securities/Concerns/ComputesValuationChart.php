<?php

namespace App\Filament\Widgets\Securities\Concerns;

use Filament\Support\RawJs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait ComputesValuationChart
{
    /**
     * @return array{0: array<int, list<array{date: string, quantity: float}>>, 1: list<array{date: string, invested: float}>, 2: list<array{date: string, fees: float}>}
     */
    protected function buildCumulatives(Collection $transactions): array
    {
        $quantities = [];
        $invested = [];
        $fees = [];
        $totalInvested = 0;
        $totalFees = 0;

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

            $totalFees += (float) $transaction->fees;
            $fees[] = [
                'date' => $date,
                'fees' => $totalFees,
            ];
        }

        return [$quantities, $invested, $fees];
    }

    /**
     * @return array{0: list<string>, 1: list<float>, 2: list<float>, 3: list<float>}
     */
    protected function computeValuations(Collection $prices, array $cumulativeQuantities, array $cumulativeInvested, array $cumulativeFees, array $securityIds): array
    {
        $days = $prices
            ->map(fn ($price) => Carbon::parse($price->date)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $pricesByDayAndSecurity = $prices->groupBy(
            fn ($p) => Carbon::parse($p->date)->format('Y-m-d'),
        )->map(fn (Collection $group) => $group->keyBy('security_id'));

        $labels = [];
        $valuations = [];
        $invested = [];
        $fees = [];

        foreach ($days as $day) {
            $valuation = 0;
            $pricesForDay = $pricesByDayAndSecurity->get($day, collect());

            foreach ($securityIds as $securityId) {
                $price = $pricesForDay->get($securityId);
                if (! $price) {
                    continue;
                }

                $quantity = $this->getQuantityAtDate($cumulativeQuantities, $securityId, $day);
                $valuation += $quantity * (float) $price->close;
            }

            $labels[] = $day;
            $valuations[] = round($valuation, 2);
            $invested[] = round($this->getInvestedAtDate($cumulativeInvested, $day), 2);
            $fees[] = round($this->getFeesAtDate($cumulativeFees, $day), 2);
        }

        return [$labels, $valuations, $invested, $fees];
    }

    protected function getQuantityAtDate(array $cumulativeQuantities, int $securityId, string $date): float
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

    protected function getInvestedAtDate(array $cumulativeInvested, string $date): float
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

    protected function getFeesAtDate(array $cumulativeFees, string $date): float
    {
        $fees = 0;
        foreach ($cumulativeFees as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $fees = $entry['fees'];
        }

        return $fees;
    }

    protected function getChartOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            callback: function(value, index, ticks) {
                                const label = this.getLabelForValue(value);
                                const date = new Date(label);
                                const month = date.toLocaleDateString('fr-FR', { month: 'short' });
                                const year = date.toLocaleDateString('fr-FR', { year: '2-digit' });
                                const key = month + year;
                                if (index > 0) {
                                    const prevLabel = this.getLabelForValue(ticks[index - 1].value);
                                    const prevDate = new Date(prevLabel);
                                    const prevKey = prevDate.toLocaleDateString('fr-FR', { month: 'short' }) + prevDate.toLocaleDateString('fr-FR', { year: '2-digit' });
                                    if (key === prevKey) return '';
                                }
                                const isMobile = this.chart.width < 500;
                                if (isMobile) {
                                    const monthIndex = date.getMonth();
                                    if (monthIndex % 2 !== 0) return '';
                                }
                                return month + ' ' + year;
                            },
                        },
                    },
                    y: {
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

    protected function buildChartDatasets(array $valuations, array $invested, array $fees, array $labels): array
    {
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
                [
                    'label' => 'Frais',
                    'data' => $fees,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => false,
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'hidden' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
