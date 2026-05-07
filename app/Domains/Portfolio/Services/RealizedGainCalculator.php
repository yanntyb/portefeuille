<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;

class RealizedGainCalculator
{
    public function calculate(Transaction $transaction): ?float
    {
        if ($transaction->type !== TransactionType::Sell) {
            return null;
        }

        $buyTransactions = Transaction::query()
            ->where('security_id', $transaction->security_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('type', TransactionType::Buy)
            ->get();

        $totalBuyQuantity = (float) $buyTransactions->sum('quantity');
        $totalBuyCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);

        $pru = $totalBuyQuantity > 0 ? $totalBuyCost / $totalBuyQuantity : 0;

        return round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
