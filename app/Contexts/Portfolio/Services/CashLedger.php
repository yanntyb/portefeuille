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
     * De quoi le cash restant est fait : ce qui vient d'un apport, et ce qui vient de chaque
     * exposition. Un débit consomme les crédits du plus ancien au plus récent — la lecture par
     * exposition d'une page se lit ici, jamais sur un second solde.
     *
     * @param  list<CashMovementData>  $movements
     * @return array{deposits: float, exposures: array<string, float>}
     */
    public function compositionAt(array $movements, string $date): array
    {
        /** @var list<array{exposure: ?string, amount: float}> $credits */
        $credits = [];

        foreach ($this->chronological($movements) as $movement) {
            if ($movement->date > $date) {
                continue;
            }

            if ($movement->delta >= 0.0) {
                $credits[] = [
                    'exposure' => $movement->exposure?->value,
                    'amount' => $movement->delta,
                ];

                continue;
            }

            $this->consume($credits, -$movement->delta);
        }

        $deposits = 0.0;
        $exposures = [];

        foreach ($credits as $credit) {
            if ($credit['amount'] <= 0.0) {
                continue;
            }

            if ($credit['exposure'] === null) {
                $deposits = round($deposits + $credit['amount'], 2);

                continue;
            }

            $exposures[$credit['exposure']] = round(($exposures[$credit['exposure']] ?? 0.0) + $credit['amount'], 2);
        }

        return ['deposits' => $deposits, 'exposures' => $exposures];
    }

    /**
     * Ce que le porteur a réellement sorti de sa poche : versements moins retraits. L'imputation
     * suit l'achat financé — réinvestir le produit d'une vente ne crée aucun apport, le sortir
     * vers une autre exposition déplace celui d'origine.
     *
     * @param  list<CashMovementData>  $movements
     * @return array{total: float, byExposure: array<string, float>}
     */
    public function netContributions(array $movements): array
    {
        /** @var list<array{exposure: ?string, amount: float}> $credits */
        $credits = [];
        $total = 0.0;
        $byExposure = [];

        foreach ($this->chronological($movements) as $movement) {
            if ($movement->delta >= 0.0) {
                $credits[] = ['exposure' => $movement->isDeposit ? null : $movement->exposure?->value, 'amount' => $movement->delta];

                if ($movement->isDeposit) {
                    $total = round($total + $movement->delta, 2);
                }

                continue;
            }

            $spent = $this->consume($credits, -$movement->delta);

            if ($movement->isWithdrawal) {
                $total = round($total - $spent['deposits'], 2);

                continue;
            }

            if ($movement->exposure !== null && $spent['deposits'] > 0.0) {
                $key = $movement->exposure->value;
                $byExposure[$key] = round(($byExposure[$key] ?? 0.0) + $spent['deposits'], 2);
            }
        }

        return ['total' => $total, 'byExposure' => $byExposure];
    }

    /**
     * Épuise les crédits les plus anciens à hauteur du débit, et rend ce qui a été pris à un apport
     * plutôt qu'à une exposition.
     *
     * @param  list<array{exposure: ?string, amount: float}>  $credits
     * @return array{deposits: float}
     */
    private function consume(array &$credits, float $debit): array
    {
        $fromDeposits = 0.0;

        foreach ($credits as $index => $credit) {
            if ($debit <= 0.0) {
                break;
            }

            $taken = min($credit['amount'], $debit);
            $credits[$index]['amount'] = round($credit['amount'] - $taken, 2);
            $debit = round($debit - $taken, 2);

            if ($credit['exposure'] === null) {
                $fromDeposits = round($fromDeposits + $taken, 2);
            }
        }

        return ['deposits' => $fromDeposits];
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
