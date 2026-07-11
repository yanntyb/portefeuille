<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

class PortfolioTransactionHistory implements TransactionHistoryPort
{
    /** @return list<TransactionRecordData> */
    public function forUser(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $transaction) => new TransactionRecordData(
                date: $transaction->date,
                assetId: (int) $transaction->asset_id,
                isSell: $transaction->type === TransactionType::Sell,
                quantity: (float) $transaction->quantity,
                unitPrice: (float) $transaction->unit_price,
                fees: (float) $transaction->fees,
            ))
            ->values()
            ->all();
    }
}
