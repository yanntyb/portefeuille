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
     * exposition, toutes enveloppes confondues. Un débit consomme les crédits du plus ancien au
     * plus récent, mais seulement ceux de sa propre enveloppe — un pool de crédits par `walletId`,
     * comme `missingDeposits()` cloisonne ses soldes, sans quoi le débit d'une enveloppe
     * consommerait le crédit d'une autre. Seule la sortie agrège toutes les enveloppes.
     *
     * @param  list<CashMovementData>  $movements
     * @return array{deposits: float, exposures: array<string, float>}
     */
    public function compositionAt(array $movements, string $date): array
    {
        /** @var array<int, list<array{exposure: ?string, amount: float}>> $creditsByWallet */
        $creditsByWallet = [];

        foreach ($this->chronological($movements) as $movement) {
            if ($movement->date > $date) {
                continue;
            }

            if ($movement->delta >= 0.0) {
                $creditsByWallet[$movement->walletId][] = [
                    'exposure' => $movement->isDeposit ? null : $movement->exposure?->value,
                    'amount' => $movement->delta,
                ];

                continue;
            }

            $credits = $creditsByWallet[$movement->walletId] ?? [];
            $this->consume($credits, -$movement->delta);
            $creditsByWallet[$movement->walletId] = $credits;
        }

        $deposits = 0.0;
        $exposures = [];

        foreach ($creditsByWallet as $credits) {
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
        }

        return ['deposits' => $deposits, 'exposures' => $exposures];
    }

    /**
     * Ce que le porteur a réellement sorti de sa poche : versements moins retraits, toutes
     * enveloppes confondues. L'imputation suit l'achat financé — réinvestir le produit d'une vente
     * ne crée aucun apport, le sortir vers une autre exposition déplace celui d'origine. La
     * consommation FIFO se cloisonne par enveloppe, comme `compositionAt()` : un achat du CTO ne
     * doit jamais consommer l'apport versé sur le PEA.
     *
     * Un retrait se retranche **en entier** du total, quelle que soit l'étiquette des crédits qu'il
     * consomme. N'en retrancher que la part d'apport prise en FIFO — ce que faisait ce calcul —
     * laissait un retrait payé par le produit d'une vente sans effet sur les apports nets :
     * `apport 1 000 → achat 1 000 → vente 1 200 → retrait 1 200` annonçait 1 000 € d'apports pour
     * une caisse vide, donc un gain de −1 000 €, quand le porteur a mis 1 000 € et repris 1 200 €.
     * La spécification est sans condition — « apports nets = versements moins retraits » — et
     * c'est le calcul qui s'en était écarté.
     *
     * Le total peut donc devenir négatif, et c'est le sens même de la situation : avoir repris plus
     * qu'on n'a mis. `byExposure` et `compositionAt()` gardent leur imputation FIFO, qui répond à
     * une autre question — d'où vient un euro, non combien il en reste dû.
     *
     * @param  list<CashMovementData>  $movements
     * @return array{total: float, byExposure: array<string, float>}
     */
    public function netContributions(array $movements): array
    {
        /** @var array<int, list<array{exposure: ?string, amount: float}>> $creditsByWallet */
        $creditsByWallet = [];
        $total = 0.0;
        $byExposure = [];

        foreach ($this->chronological($movements) as $movement) {
            if ($movement->delta >= 0.0) {
                $creditsByWallet[$movement->walletId][] = ['exposure' => $movement->isDeposit ? null : $movement->exposure?->value, 'amount' => $movement->delta];

                if ($movement->isDeposit) {
                    $total = round($total + $movement->delta, 2);
                }

                continue;
            }

            $credits = $creditsByWallet[$movement->walletId] ?? [];
            $spent = $this->consume($credits, -$movement->delta);
            $creditsByWallet[$movement->walletId] = $credits;

            if ($movement->isWithdrawal) {
                $total = round($total + $movement->delta, 2);

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
     * Le solde et les apports nets à chacune des dates demandées, en un seul balayage.
     *
     * Une série de N points se lisait autrefois en rappelant `netContributions()` — tri compris —
     * une fois par point, sur un tableau refiltré à chaque tour : quadratique, mesuré à 72 ms pour
     * 85 points et de l'ordre de trois secondes à un millier de transactions, sur le chemin du
     * tableau de bord. Les mouvements et les dates étant tous deux triés, un curseur qui n'avance
     * jamais en arrière rend exactement les mêmes valeurs.
     *
     * @param  list<CashMovementData>  $movements
     * @param  list<string>  $dates  Dates croissantes.
     * @return list<array{balance: float, netContributions: float}>
     */
    public function timeline(array $movements, array $dates): array
    {
        $chronological = $this->chronological($movements);
        $count = count($chronological);
        $cursor = 0;

        /** @var array<int, list<array{exposure: ?string, amount: float}>> $creditsByWallet */
        $creditsByWallet = [];
        $balance = 0.0;
        $total = 0.0;
        $points = [];

        foreach ($dates as $date) {
            while ($cursor < $count && $chronological[$cursor]->date <= $date) {
                $movement = $chronological[$cursor];
                $balance = round($balance + $movement->delta, 2);

                if ($movement->delta >= 0.0) {
                    $creditsByWallet[$movement->walletId][] = [
                        'exposure' => $movement->isDeposit ? null : $movement->exposure?->value,
                        'amount' => $movement->delta,
                    ];

                    if ($movement->isDeposit) {
                        $total = round($total + $movement->delta, 2);
                    }
                } else {
                    $credits = $creditsByWallet[$movement->walletId] ?? [];
                    $this->consume($credits, -$movement->delta);
                    $creditsByWallet[$movement->walletId] = $credits;

                    if ($movement->isWithdrawal) {
                        $total = round($total + $movement->delta, 2);
                    }
                }

                $cursor++;
            }

            $points[] = ['balance' => $balance, 'netContributions' => $total];
        }

        return $points;
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
