<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;
use Illuminate\Database\Eloquent\Builder;

class PortfolioLedger implements TransactionsPort
{
    public function __construct(private TransactionFlow $flow) {}

    /** @return list<WealthTransactionLineData> */
    public function transactionsFor(int $userId): array
    {
        return $this->read($userId, null);
    }

    /** @return list<WealthTransactionLineData> */
    /**
     * La jointure nomme l'actif en une requête : une ligne par opération, chacune chargeant son
     * actif rouvrirait un N+1 sur tout l'historique.
     *
     * `leftJoin` et non `join` : un versement ou un retrait n'a pas d'`asset_id`, et le tableau de
     * bord doit quand même les afficher — c'est le seul des journaux à le faire, ceux de
     * `PortfolioView` restant scopés à un actif ou à un périmètre de positions.
     *
     * @return list<WealthTransactionLineData>
     */
    private function read(int $userId, ?int $walletId): array
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->when($walletId !== null, fn (Builder $query): Builder => $query->where('transactions.wallet_id', $walletId))
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
