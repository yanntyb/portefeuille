<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesValuationChart;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Livewire\Attributes\On;

class ValuationChartWidget extends ChartWidget
{
    use ComputesValuationChart;
    use InteractsWithPageTable;

    protected ?string $heading = 'Evolution de la valorisation';

    protected int|string|array $columnSpan = 'full';

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

        if ($this->shownSecurityIds !== null) {
            $securityIds = array_values(array_intersect($securityIds, $this->shownSecurityIds));
        }

        if (empty($securityIds)) {
            return ['datasets' => [], 'labels' => []];
        }

        $transactions = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->where('user_id', auth()->id())
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

        [$labels, $valuations, $invested, $fees] = $this->computeValuations($prices, $cumulativeQuantities, $cumulativeInvested, $cumulativeFees, $securityIds);

        return $this->buildChartDatasets($valuations, $invested, $fees, $labels);
    }

    protected function getOptions(): RawJs
    {
        return $this->getChartOptions();
    }
}
