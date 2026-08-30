<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\CostBasis;

class CalculateRealizedGain
{
    public function __construct(private CostBasis $costBasis) {}

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

        $pru = $this->costBasis->of(
            $buys->map(fn (Transaction $t): array => [
                'quantity' => (float) $t->quantity,
                'unitPrice' => (float) $t->unit_price,
                'fees' => (float) $t->fees,
            ])->values()->all(),
        )['average'];

        return round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
