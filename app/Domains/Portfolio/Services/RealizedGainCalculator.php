<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;

class RealizedGainCalculator
{
    public function __construct(private TransactionRepositoryInterface $transactionRepository) {}

    public function calculate(Transaction $transaction): ?float
    {
        if ($transaction->type !== TransactionType::Sell) {
            return null;
        }

        $transaction->loadMissing('wallet');
        $buyTransactions = $this->transactionRepository
            ->forWallet($transaction->wallet_id, $transaction->wallet->user_id)
            ->filter(fn ($t) => $t->asset_id === $transaction->asset_id && $t->type === TransactionType::Buy);

        $totalBuyQuantity = (float) $buyTransactions->sum('quantity');
        $totalBuyCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);

        $pru = $totalBuyQuantity > 0 ? $totalBuyCost / $totalBuyQuantity : 0;

        return round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
