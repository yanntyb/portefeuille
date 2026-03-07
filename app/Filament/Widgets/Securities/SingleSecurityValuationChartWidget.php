<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesValuationChart;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Services\TransactionAggregator;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityValuationChartWidget extends ChartWidget
{
    use ComputesValuationChart;

    protected ?string $heading = 'Evolution de la valorisation';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public ?Model $record = null;

    public ?string $accountType = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $securityIds = [$this->record->id];

        $transactionsQuery = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date');

        if ($this->accountType) {
            $transactionsQuery->where('account_type', $this->accountType);
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
        return $this->getChartOptions();
    }
}
