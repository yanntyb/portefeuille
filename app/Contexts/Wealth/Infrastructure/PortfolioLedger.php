<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;

class PortfolioLedger implements TransactionsPort
{
    /**
     * La jointure nomme l'actif en une requête : une ligne par opération, chacune chargeant son
     * actif rouvrirait un N+1 sur tout l'historique.
     *
     * `asset_id` est nullable en base ; une opération sans actif n'a rien à nommer et ne prend pas
     * de ligne.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsFor(int $userId): array
    {
        return Transaction::query()
            ->join('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name')
            ->get()
            ->map(function (Transaction $transaction): WealthTransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;

                return new WealthTransactionLineData(
                    date: $transaction->date->format('Y-m-d'),
                    assetId: (int) $transaction->asset_id,
                    assetName: (string) $transaction->getAttribute('asset_name'),
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
