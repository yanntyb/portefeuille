<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Models\Transaction;

/**
 * Efface une opération. `TransactionObserver` reprojette au `deleted` : la position tombe de la
 * quantité effacée — jusqu'à disparaître si elle s'en trouve vidée — et les ventes qui s'appuyaient
 * sur cet achat retrouvent le prix de revient de ceux qui restent.
 */
class DeleteTransaction
{
    public function __invoke(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
