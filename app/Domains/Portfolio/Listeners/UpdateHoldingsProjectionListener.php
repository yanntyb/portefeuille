<?php

namespace App\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\HoldingsProjection;
use App\Domains\Portfolio\Models\Transaction;

class UpdateHoldingsProjectionListener
{
    public function handle(TransactionCreated $event): void
    {
        $transaction = $event->transaction;

        $projection = HoldingsProjection::updateOrCreate(
            [
                'asset_id' => $transaction->asset_id,
                'wallet_id' => $transaction->wallet_id,
            ],
            [
                'user_id' => $transaction->user_id,
            ]
        );

        // Recalculate quantity and average cost from all buy transactions
        $transactions = Transaction::withoutGlobalScope('user')
            ->where('asset_id', $transaction->asset_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('type', TransactionType::Buy)
            ->get();

        $totalQuantity = (float) $transactions->sum('quantity');
        $totalCost = (float) $transactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);

        $avgCost = $totalQuantity > 0 ? $totalCost / $totalQuantity : null;

        $projection->update([
            'quantity' => $totalQuantity,
            'avg_cost' => $avgCost,
        ]);
    }
}
