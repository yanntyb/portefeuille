<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\CashLedger;

/**
 * Les versements déduits d'une enveloppe, réécrits d'un bloc.
 *
 * Même raison que `RecomputeRealizedGains` : une ligne déduite est la conséquence d'un achat,
 * donc tout achat corrigé, déplacé ou supprimé la rend fausse. On efface ce que le système a
 * écrit, on rejoue l'historique des seuls faits saisis, et on réécrit ce qui manque encore.
 *
 * Les lignes saisies à la main (`auto = false`) ne sont jamais touchées : saisir après coup un
 * vrai virement fait donc disparaître de lui-même le versement déduit qu'il couvre.
 *
 * Écriture par `saveQuietly()` / `withoutEvents()` : l'observateur se rappellerait sans fin.
 */
class RecomputeCashDeposits
{
    public function __construct(
        private GetCashMovements $movements,
        private CashLedger $ledger,
    ) {}

    public function __invoke(int $userId, int $walletId): void
    {
        Transaction::withoutEvents(function () use ($userId, $walletId): void {
            Transaction::query()
                ->where('user_id', $userId)
                ->where('wallet_id', $walletId)
                ->where('auto', true)
                ->delete();

            /** Le cache tiendrait sinon les lignes déduites qu'on vient de supprimer. */
            $this->movements->forget($userId);

            /** Les faits saisis seuls : repartir des lignes déduites reconduirait les périmées. */
            $movements = ($this->movements)($userId, includeAuto: false);

            foreach ($this->ledger->missingDeposits($movements) as $deposit) {
                if ($deposit['walletId'] !== $walletId) {
                    continue;
                }

                /** Tableau littéral : `$guarded = ['id']` laisserait tout passer en assignation de masse. */
                Transaction::query()->create([
                    'user_id' => $userId,
                    'wallet_id' => $walletId,
                    'asset_id' => null,
                    'date' => $deposit['date'],
                    'type' => TransactionType::Deposit,
                    'quantity' => null,
                    'unit_price' => null,
                    'fees' => 0,
                    'amount' => $deposit['amount'],
                    'auto' => true,
                ]);
            }

            /** L'écriture qui précède ne doit pas non plus rester dans le cache. */
            $this->movements->forget($userId);
        });
    }
}
