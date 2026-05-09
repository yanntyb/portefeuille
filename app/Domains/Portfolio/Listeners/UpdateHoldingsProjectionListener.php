<?php

namespace App\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\HoldingsProjection;
use App\Domains\Portfolio\Models\Transaction;
use Illuminate\Database\Query\JoinClause;

class UpdateHoldingsProjectionListener
{
    public function handle(TransactionCreated $event): void
    {
        $transaction = $event->transaction;

        // Skip if no user_id or asset_id
        if ($transaction->user_id === null || $transaction->asset_id === null) {
            return;
        }

        // Calculate total quantity and average cost for this asset/wallet pair
        $totals = Transaction::forUser($transaction->user_id)
            ->where('asset_id', $transaction->asset_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('type', 'buy')
            ->selectRaw('SUM(quantity) as total_qty, SUM(quantity * unit_price) as total_cost')
            ->first();

        // Calculate sells (reduce quantity)
        $sells = Transaction::forUser($transaction->user_id)
            ->where('asset_id', $transaction->asset_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('type', 'sell')
            ->selectRaw('SUM(quantity) as total_sold')
            ->first();

        $quantity = ($totals->total_qty ?? 0) - ($sells->total_sold ?? 0);

        if ($quantity <= 0) {
            HoldingsProjection::where('asset_id', $transaction->asset_id)
                ->where('wallet_id', $transaction->wallet_id)
                ->delete();
            return;
        }

        $avgCost = ($totals->total_qty ?? 0) > 0
            ? (float) ($totals->total_cost ?? 0) / (float) ($totals->total_qty ?? 1)
            : 0;

        HoldingsProjection::query()
            ->where('asset_id', $transaction->asset_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->update([
                'user_id' => $transaction->user_id,
                'quantity' => $quantity,
                'avg_cost' => $avgCost,
            ]);

        // If no row was updated, create one
        if (HoldingsProjection::where('asset_id', $transaction->asset_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->count() === 0) {
            HoldingsProjection::create([
                'user_id' => $transaction->user_id,
                'asset_id' => $transaction->asset_id,
                'wallet_id' => $transaction->wallet_id,
                'quantity' => $quantity,
                'avg_cost' => $avgCost,
            ]);
        }
    }
}
