<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

class ProjectHolding
{
    public function __invoke(int $userId, int $assetId, int $walletId): void
    {
        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->where('wallet_id', $walletId)
            ->get();

        $buys = $transactions->where('type', TransactionType::Buy);
        $sells = $transactions->where('type', TransactionType::Sell);

        $buyQty = (float) $buys->sum('quantity');
        $buyCost = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity * (float) $t->unit_price);
        $soldQty = (float) $sells->sum('quantity');

        $quantity = $buyQty - $soldQty;

        if ($quantity <= 0.0) {
            Holding::query()
                ->where('asset_id', $assetId)
                ->where('wallet_id', $walletId)
                ->delete();

            return;
        }

        Holding::query()->updateOrCreate(
            ['asset_id' => $assetId, 'wallet_id' => $walletId],
            [
                'user_id' => $userId,
                'quantity' => $quantity,
                'avg_cost' => $buyQty > 0.0 ? $buyCost / $buyQty : 0.0,
            ],
        );
    }
}
