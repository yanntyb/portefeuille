<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Enums\TransactionType;

/**
 * Le flux de trésorerie d'une transaction : ce qui est réellement sorti du compte à l'achat, ce qui
 * y est réellement entré à la vente, ce qu'un mouvement d'espèces porte tel quel. Les frais grèvent
 * un ordre dans les deux sens — ils s'ajoutent à ce que coûte un achat et se retranchent de ce que
 * rapporte une vente ; un mouvement d'espèces n'en porte pas.
 *
 * Le montant rendu par `of()` est toujours positif : c'est le sens de l'opération, porté à part par
 * `cashDelta()`, qui décide de son signe à l'affichage comme au solde d'une année.
 */
class TransactionFlow
{
    /**
     * Le montant brut d'une opération. Un ordre le tire de la quantité et du prix, un mouvement
     * d'espèces le tire de `$amount` saisi ; `abs()` protège des deux mêmes raisons que dans
     * `cashDelta()` — `$amount` n'est jamais signé en base.
     */
    public function of(TransactionType $type, ?float $quantity, ?float $unitPrice, float $fees, ?float $amount): float
    {
        return match ($type) {
            TransactionType::Buy => (float) $quantity * (float) $unitPrice + $fees,
            TransactionType::Sell => (float) $quantity * (float) $unitPrice - $fees,
            TransactionType::Deposit, TransactionType::Withdrawal, TransactionType::Dividend => abs((float) $amount),
        };
    }

    /**
     * Le mouvement d'espèces d'une opération, signé : positif quand l'argent entre sur le compte,
     * négatif quand il en sort. Site unique du signe, `of()` restant celui du seul montant.
     */
    public function cashDelta(TransactionType $type, ?float $quantity, ?float $unitPrice, float $fees, ?float $amount): float
    {
        $magnitude = $this->of($type, $quantity, $unitPrice, $fees, $amount);

        return match ($type) {
            TransactionType::Buy, TransactionType::Withdrawal => -$magnitude,
            TransactionType::Sell, TransactionType::Deposit, TransactionType::Dividend => $magnitude,
        };
    }
}
