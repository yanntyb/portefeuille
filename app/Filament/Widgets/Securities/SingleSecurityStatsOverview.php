<?php

namespace App\Filament\Widgets\Securities;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public ?Model $record = null;

    public ?string $accountType = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $transactionsQuery = Transaction::query()
            ->where('security_id', $this->record->id);

        if ($this->accountType) {
            $transactionsQuery->where('account_type', $this->accountType);
        }

        $transactions = $transactionsQuery->get();

        $totalQuantity = (float) $transactions->sum('quantity');
        $totalFees = (float) $transactions->sum('fees');
        $totalInvested = $transactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price) + $totalFees;
        $pru = $totalQuantity > 0
            ? $transactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price) / $totalQuantity
            : 0;

        $this->record->loadMissing('latestPrice');
        $close = $this->record->latestPrice?->close;
        $valuation = ($close !== null) ? $totalQuantity * (float) $close : 0;

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $plusValueLabel = Number::currency($plusValue, 'EUR').' ('.Number::format($plusValuePercentage, 2).' %)';
        $feesLabel = Number::currency($totalFees, 'EUR').' ('.Number::format($feesPercentage, 2).' %)';

        return [
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
            Stat::make('Plus-value', $plusValueLabel)
                ->color($plusValue >= 0 ? 'success' : 'danger'),
            Stat::make('PRU', Number::currency($pru, 'EUR')),
            Stat::make('Frais', $feesLabel)
                ->color('danger'),
        ];
    }
}
