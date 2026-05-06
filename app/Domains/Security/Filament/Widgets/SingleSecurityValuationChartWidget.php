<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Services\TransactionAggregator;
use App\Domains\Security\Models\SecurityPrice;
use App\Infrastructure\Filament\Concerns\ComputesValuationChart;
use App\Infrastructure\Filament\Widgets\ChartWidget;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityValuationChartWidget extends ChartWidget
{
    use ComputesValuationChart;

    protected ?string $heading = 'Valorisation';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.bare-chart-widget';

    protected ?string $maxHeight = '200px';

    public ?Model $record = null;

    public ?int $walletId = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $securityIds = [$this->record->id];

        $transactionsQuery = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date');

        if ($this->walletId) {
            $transactionsQuery->where('wallet_id', $this->walletId);
        }

        $transactions = $transactionsQuery->get();

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

        $result = $aggregator->computeDailyValuations($prices, $cumulative, $securityIds);

        return $this->buildChartDatasets($result->valuations, $result->invested, $result->fees, $result->labels);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
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
