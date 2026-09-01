<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Datas\TransactionInputData;
use App\Contexts\Portfolio\Models\Transaction;

/**
 * Enregistre une opération. Rien de plus : `TransactionObserver` pose le gain réalisé au `creating`
 * puis reprojette la position et les gains de l'enveloppe au `created`.
 */
class CreateTransaction
{
    public function __invoke(int $userId, TransactionInputData $input): Transaction
    {
        /**
         * Tableau littéral et jamais `create($validated)` : `$guarded = ['id']` laisserait passer
         * `user_id` et `realized_gain` en assignation de masse. `user_id` vient de la session,
         * `realized_gain` de l'observateur — ni l'un ni l'autre d'une requête.
         */
        return Transaction::query()->create([
            'user_id' => $userId,
            'wallet_id' => $input->walletId,
            'asset_id' => $input->assetId,
            'date' => $input->date,
            'type' => $input->type,
            'quantity' => $input->quantity,
            'unit_price' => $input->unitPrice,
            'fees' => $input->fees,
            'amount' => $input->amount,
        ]);
    }
}
