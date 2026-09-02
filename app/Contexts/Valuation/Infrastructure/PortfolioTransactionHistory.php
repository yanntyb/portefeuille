<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

class PortfolioTransactionHistory implements TransactionHistoryPort
{
    /**
     * Toutes les transactions de l'utilisateur, versements et retraits compris : un mouvement
     * d'espèces sans actif ne détient aucune position, mais il traverse le filtre par exposition
     * de `BuildExposureSeries`, qui ne doit écarter que ce qui appartient à une autre classe.
     *
     * La jointure sur `assets` porte l'exposition de l'actif concerné ; `leftJoin` et non `join`,
     * puisqu'un versement ou un retrait n'a pas d'`asset_id`.
     *
     * @return list<TransactionRecordData>
     */
    public function forUser(int $userId): array
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->orderBy('transactions.date')
            ->select('transactions.*', 'assets.asset_class as exposure')
            ->get()
            ->map(function (Transaction $transaction): TransactionRecordData {
                $quantity = $transaction->quantity === null ? null : (float) $transaction->quantity;
                $unitPrice = $transaction->unit_price === null ? null : (float) $transaction->unit_price;
                $fees = (float) $transaction->fees;
                $amount = $transaction->amount === null ? null : (float) $transaction->amount;
                $exposure = $transaction->getAttribute('exposure');

                return new TransactionRecordData(
                    date: $transaction->date,
                    assetId: $transaction->asset_id === null ? null : (int) $transaction->asset_id,
                    walletId: (int) $transaction->wallet_id,
                    type: $transaction->type,
                    isSell: $transaction->type === TransactionType::Sell,
                    quantity: $quantity ?? 0.0,
                    unitPrice: $unitPrice ?? 0.0,
                    fees: $fees,
                    amount: $amount,
                    exposure: $exposure === null ? null : AssetClass::from($exposure),
                );
            })
            ->values()
            ->all();
    }
}
