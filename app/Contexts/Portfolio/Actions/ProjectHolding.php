<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Models\Holding;

class ProjectHolding
{
    public function __construct(private GetPositionStock $stock) {}

    public function __invoke(int $userId, int $assetId, int $walletId): void
    {
        $stock = ($this->stock)($userId, $assetId, $walletId);

        if ($stock['quantity'] <= 0.0) {
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
                'quantity' => $stock['quantity'],
                'avg_cost' => $stock['avgCost'],
            ],
        );
    }
}
