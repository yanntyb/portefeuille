<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

class CalculateRealizedGain
{
    public function __invoke(Transaction $transaction): ?float
    {
        if ($transaction->type !== TransactionType::Sell) {
            return null;
        }

        $buys = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('asset_id', $transaction->asset_id)
            ->where('type', TransactionType::Buy)
            ->where('date', '<=', $transaction->date)
            ->get();

        $buyQty = (float) $buys->sum('quantity');
        $buyCost = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity * (float) $t->unit_price);
        $pru = $buyQty > 0.0 ? $buyCost / $buyQty : 0.0;

        return round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
