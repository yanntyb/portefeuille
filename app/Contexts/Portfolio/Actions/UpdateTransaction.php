<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Datas\TransactionInputData;
use App\Contexts\Portfolio\Models\Transaction;

/**
 * Corrige une opération. `TransactionObserver` recalcule le gain réalisé au `updating`, puis
 * reprojette au `updated` la position et les gains — de la nouvelle enveloppe, et de l'ancienne
 * quand la ligne a déménagé.
 */
class UpdateTransaction
{
    public function __invoke(Transaction $transaction, TransactionInputData $input): Transaction
    {
        /**
         * `user_id` est absent de la liste, et immuable : on corrige une opération, on ne la donne
         * pas à quelqu'un d'autre. Même garde qu'à la création face à `$guarded = ['id']`.
         */
        $transaction->update([
            'wallet_id' => $input->walletId,
            'asset_id' => $input->assetId,
            'date' => $input->date,
            'type' => $input->type,
            'quantity' => $input->quantity,
            'unit_price' => $input->unitPrice,
            'fees' => $input->fees,
            'amount' => $input->amount,
        ]);

        return $transaction;
    }
}
