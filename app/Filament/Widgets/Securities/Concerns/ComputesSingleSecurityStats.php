<?php

namespace App\Filament\Widgets\Securities\Concerns;

use App\Enums\TransactionType;
use App\Models\Transaction;

/**
 * @property ?\Illuminate\Database\Eloquent\Model $record
 * @property ?string $accountType
 */
trait ComputesSingleSecurityStats
{
    /**
     * @return array{
     *     totalQuantity: float,
     *     pru: float,
     *     totalFees: float,
     *     totalInvested: float,
     *     totalRealizedGain: float,
     *     valuation: float,
     *     plusValue: float,
     *     plusValuePercentage: float,
     *     feesPercentage: float,
     *     close: ?float,
     *     priceDate: ?string,
     * }
     */
    protected function computeStats(): array
    {
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
            'totalQuantity' => $totalQuantity,
            'pru' => $pru,
            'totalFees' => $totalFees,
            'totalInvested' => $totalInvested,
            'totalRealizedGain' => $totalRealizedGain,
            'valuation' => $valuation,
            'plusValue' => $plusValue,
            'plusValuePercentage' => $plusValuePercentage,
            'feesPercentage' => $feesPercentage,
            'close' => $close !== null ? (float) $close : null,
            'priceDate' => $priceDate,
        ];
    }
}
