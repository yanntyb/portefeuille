<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\MarketView\Datas\TransactionLineData;
use App\Contexts\MarketView\Ports\TransactionsPort;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

class PortfolioTransactions implements TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->orderByDesc('date')
            ->get()
            ->map(function (Transaction $transaction): TransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;

                return new TransactionLineData(
                    date: $transaction->date->format('Y-m-d'),
                    isSell: $transaction->type === TransactionType::Sell,
                    typeLabel: $transaction->type->getLabel(),
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: (float) $transaction->fees,
                    total: $quantity * $unitPrice,
                );
            })
            ->values()
            ->all();
    }
}
