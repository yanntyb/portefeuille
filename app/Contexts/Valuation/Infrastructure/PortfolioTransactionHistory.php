<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

class PortfolioTransactionHistory implements TransactionHistoryPort
{
    public function __construct(private TransactionFlow $flow) {}

    /**
     * Toutes les transactions de l'utilisateur, versements et retraits compris : un mouvement
     * d'espèces sans actif ne détient aucune position, mais alimente quand même la série de
     * liquidités. Le calcul du sens du mouvement (`cashDelta`) se fait ici plutôt que dans
     * `ValuationCalculator`, qui n'a pas le droit d'importer un service de `Portfolio`.
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
                    type: $transaction->type,
                    isSell: $transaction->type === TransactionType::Sell,
                    quantity: $quantity ?? 0.0,
                    unitPrice: $unitPrice ?? 0.0,
                    fees: $fees,
                    cashDelta: $this->flow->cashDelta($transaction->type, $quantity, $unitPrice, $fees, $amount),
                    amount: $amount,
                    exposure: $exposure === null ? null : AssetClass::from($exposure),
                );
            })
            ->values()
            ->all();
    }
}
