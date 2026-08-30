<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;

class PortfolioLedger implements TransactionsPort
{
    public function __construct(private TransactionFlow $flow) {}

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
                $fees = (float) $transaction->fees;
                $isSell = $transaction->type === TransactionType::Sell;

                return new WealthTransactionLineData(
                    date: $transaction->date->format('Y-m-d'),
                    assetId: (int) $transaction->asset_id,
                    assetName: (string) $transaction->getAttribute('asset_name'),
                    isSell: $isSell,
                    typeLabel: $transaction->type->getLabel(),
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: $fees,
                    total: $this->flow->of($quantity, $unitPrice, $fees, $isSell),
                );
            })
            ->values()
            ->all();
    }
}
