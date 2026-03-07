<?php

namespace App\Filament\Widgets\Securities;

use App\Enums\TransactionType;
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

        $buyTransactions = $transactions->where('type', TransactionType::Buy);
        $sellTransactions = $transactions->where('type', TransactionType::Sell);

        $totalBuyQuantity = (float) $buyTransactions->sum('quantity');
        $totalSellQuantity = (float) $sellTransactions->sum('quantity');
        $totalQuantity = $totalBuyQuantity - $totalSellQuantity;

        $totalBuyCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);
        $pru = $totalBuyQuantity > 0 ? $totalBuyCost / $totalBuyQuantity : 0;

        $totalFees = (float) $transactions->sum('fees');
        $totalInvested = $totalQuantity * $pru + $totalFees;

        $totalRealizedGain = (float) $sellTransactions->sum('realized_gain');

        $this->record->loadMissing('latestPrice');
        $close = $this->record->latestPrice?->close;
        $valuation = ($close !== null) ? $totalQuantity * (float) $close : 0;

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $priceDate = $this->record->latestPrice?->date?->translatedFormat('d M Y');

        return [
            Stat::make('Prix actuel', $close !== null ? Number::currency($close, 'EUR') : '—')
                ->description($priceDate),
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
            Stat::make('Plus-value latente', Number::currency($plusValue, 'EUR'))
                ->description(Number::format($plusValuePercentage, 2).' %')
                ->color($plusValue >= 0 ? 'success' : 'danger'),
            Stat::make('PRU', Number::currency($pru, 'EUR')),
            Stat::make('Frais', Number::currency($totalFees, 'EUR'))
                ->description(Number::format($feesPercentage, 2).' %')
                ->color('danger'),
            Stat::make('Plus-value réalisée', Number::currency($totalRealizedGain, 'EUR'))
                ->color($totalRealizedGain >= 0 ? 'success' : 'danger'),
        ];
    }
}
