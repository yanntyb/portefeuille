<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesValuationChart;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityValuationChartWidget extends ChartWidget
{
    use ComputesValuationChart;

    protected ?string $heading = 'Evolution de la valorisation';

    protected int|string|array $columnSpan = 2;

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
            ->orderBy('date')
            ->select(['security_id', 'date', 'quantity', 'unit_price', 'fees']);

        if ($this->accountType) {
            $transactionsQuery->where('account_type', $this->accountType);
        }

        $transactions = $transactionsQuery->get();

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

        [$labels, $valuations, $invested, $fees] = $this->computeValuations($prices, $cumulativeQuantities, $cumulativeInvested, $cumulativeFees, $securityIds);

        return $this->buildChartDatasets($valuations, $invested, $fees, $labels);
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
