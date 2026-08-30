<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\CostBasis;

class ProjectHolding
{
    public function __construct(private CostBasis $costBasis) {}

    public function __invoke(int $userId, int $assetId, int $walletId): void
    {
        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->where('wallet_id', $walletId)
            ->get();

        $buys = $this->costBasis->of(
            $transactions->where('type', TransactionType::Buy)
                ->map(fn (Transaction $t): array => [
                    'quantity' => (float) $t->quantity,
                    'unitPrice' => (float) $t->unit_price,
                    'fees' => (float) $t->fees,
                ])
                ->values()
                ->all(),
        );

        $soldQty = (float) $transactions->where('type', TransactionType::Sell)->sum('quantity');
        $quantity = $buys['quantity'] - $soldQty;

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
                'avg_cost' => $buys['average'],
            ],
        );
    }
}
