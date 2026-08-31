<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\CashMovementData;

/**
 * Le compte espèces d'une enveloppe : ce qu'elle détient en liquide à une date, et ce qu'il a
 * fallu y verser pour que chaque achat soit financé.
 *
 * Rien n'est stocké — un solde est une somme de mouvements, comme l'investi est une somme
 * d'achats. Le service reste pur : il ne lit que des `CashMovementData`, jamais la base.
 *
 * Invariant qui gouverne tout : le solde d'une enveloppe n'est négatif à aucune date de son
 * historique. Un achat que le cash ne couvre pas a été financé par un apport ; `missingDeposits()`
 * rend cet apport, que `RecomputeCashDeposits` écrit.
 */
class CashLedger
{
    /**
     * Le solde d'une enveloppe au soir d'une date.
     *
     * @param  list<CashMovementData>  $movements
     */
    public function balanceAt(array $movements, int $walletId, string $date): float
    {
        $balance = 0.0;

        foreach ($movements as $movement) {
            if ($movement->walletId === $walletId && $movement->date <= $date) {
                $balance += $movement->delta;
            }
        }

        return round($balance, 2);
    }

    /**
     * Les versements qu'il faut déduire pour qu'aucun solde ne passe négatif, dans l'ordre
     * chronologique. Un seul passage par enveloppe : le manque se comble à la date où il apparaît,
     * donc un versement déduit finance aussi tout ce qui suit tant qu'il reste du solde.
     *
     * @param  list<CashMovementData>  $movements
     * @return list<array{walletId: int, date: string, amount: float}>
     */
    public function missingDeposits(array $movements): array
    {
        $balances = [];
        $deposits = [];

        foreach ($this->chronological($movements) as $movement) {
            $balance = ($balances[$movement->walletId] ?? 0.0) + $movement->delta;

            if ($balance < 0.0) {
                $missing = round(-$balance, 2);
                $deposits[] = [
                    'walletId' => $movement->walletId,
                    'date' => $movement->date,
                    'amount' => $missing,
                ];
                $balance = 0.0;
            }

            $balances[$movement->walletId] = round($balance, 2);
        }

        return $deposits;
    }

    /**
     * Les crédits avant les débits à date égale : un achat et la vente qui le finance saisis le
     * même jour ne doivent pas déduire un versement pour un manque d'un instant.
     *
     * @param  list<CashMovementData>  $movements
     * @return list<CashMovementData>
     */
    private function chronological(array $movements): array
    {
        usort($movements, fn (CashMovementData $a, CashMovementData $b): int => ($a->date <=> $b->date)
            ?: (($a->delta < 0 ? 1 : 0) <=> ($b->delta < 0 ? 1 : 0)));

        return $movements;
    }
}
