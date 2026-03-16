<?php

namespace App\Observers;

use App\Enums\TransactionType;
use App\Models\Transaction;

class TransactionObserver
{
    public function creating(Transaction $transaction): void
    {
        $this->syncRealizedGain($transaction);
    }

    public function updating(Transaction $transaction): void
    {
        $this->syncRealizedGain($transaction);
    }

    private function syncRealizedGain(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Sell) {
            $transaction->realized_gain = null;

            return;
        }

        $buyTransactions = Transaction::query()
            ->where('security_id', $transaction->security_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('type', TransactionType::Buy)
            ->get();

        $totalBuyQuantity = (float) $buyTransactions->sum('quantity');
        $totalBuyCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);

        $pru = $totalBuyQuantity > 0 ? $totalBuyCost / $totalBuyQuantity : 0;

        $transaction->realized_gain = round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
