<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use RuntimeException;

/**
 * Encaisse un détachement attendu : il devient une transaction de dividende, que
 * `TransactionObserver` verse dans le compte espèces de l'enveloppe.
 *
 * Le montant est celui saisi par l'utilisateur, pas celui calculé par `DividendCalculator` — le
 * calcul propose un brut, l'utilisateur corrige au net réellement reçu. Aucune fiscalité n'est
 * calculée : c'est le montant validé qui fait foi.
 */
class ConfirmDividend
{
    public function __invoke(int $userId, int $walletId, int $assetId, string $exDate, float $amount): Transaction
    {
        $alreadyConfirmed = Transaction::query()
            ->where('user_id', $userId)
            ->where('wallet_id', $walletId)
            ->where('asset_id', $assetId)
            ->where('type', TransactionType::Dividend)
            ->whereDate('date', $exDate)
            ->exists();

        if ($alreadyConfirmed) {
            throw new RuntimeException('Ce dividende a déjà été encaissé pour cette enveloppe et cette date.');
        }

        /**
         * Tableau littéral et jamais `create($validated)` : `$guarded = ['id']` laisserait passer
         * `user_id` en assignation de masse. Ni quantité ni prix : un dividende est un montant
         * reçu, pas un échange.
         */
        return Transaction::query()->create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'asset_id' => $assetId,
            'date' => $exDate,
            'type' => TransactionType::Dividend,
            'quantity' => null,
            'unit_price' => null,
            'fees' => 0,
            'amount' => $amount,
        ]);
    }
}
