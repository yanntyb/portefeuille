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
     * `leftJoin` et non `join` : un versement ou un retrait n'a pas d'`asset_id`, et le tableau de
     * bord doit quand même les afficher — c'est le seul des trois journaux à le faire, les deux
     * jumelles de `MarketView` restant scopées à un actif ou une exposition.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsFor(int $userId): array
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name')
            ->get()
            ->map(function (Transaction $transaction): WealthTransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;
                $fees = (float) $transaction->fees;
                $amount = $transaction->amount === null ? null : (float) $transaction->amount;
                $isSell = $transaction->type === TransactionType::Sell;

                return new WealthTransactionLineData(
                    id: $transaction->id,
                    walletId: $transaction->wallet_id,
                    date: $transaction->date->format('Y-m-d'),
                    assetId: $transaction->asset_id === null ? null : (int) $transaction->asset_id,
                    assetName: $transaction->getAttribute('asset_name'),
                    isSell: $isSell,
                    typeLabel: $transaction->type->getLabel(),
                    type: $transaction->type->value,
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: $fees,
                    total: $this->flow->of($transaction->type, $quantity, $unitPrice, $fees, $amount),
                    auto: (bool) $transaction->auto,
                );
            })
            ->values()
            ->all();
    }
}
