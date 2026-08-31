<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Enums\TransactionType;

/**
 * Le flux de trésorerie d'une transaction : ce qui est réellement sorti du compte à l'achat, ce qui
 * y est réellement entré à la vente. Les frais grèvent l'opération dans les deux sens — ils
 * s'ajoutent à ce que coûte un achat et se retranchent de ce que rapporte une vente.
 *
 * Le montant rendu est toujours positif : c'est le sens de l'opération, porté à part, qui décide de
 * son signe à l'affichage comme au solde d'une année.
 */
class TransactionFlow
{
    public function of(float $quantity, float $unitPrice, float $fees, bool $isSell): float
    {
        $gross = $quantity * $unitPrice;

        return $isSell ? $gross - $fees : $gross + $fees;
    }

    /**
     * Le mouvement d'espèces d'une opération, signé : positif quand l'argent entre sur le compte,
     * négatif quand il en sort. Site unique du signe, comme `of()` est celui du montant.
     *
     * La valeur absolue est prise sur `$amount` : `amount` n'est jamais signée en base, et un
     * montant négatif saisi par erreur ne doit pas retourner le sens de l'opération.
     */
    public function cashDelta(TransactionType $type, ?float $quantity, ?float $unitPrice, float $fees, ?float $amount): float
    {
        return match ($type) {
            TransactionType::Buy => -$this->of((float) $quantity, (float) $unitPrice, $fees, false),
            TransactionType::Sell => $this->of((float) $quantity, (float) $unitPrice, $fees, true),
            TransactionType::Deposit, TransactionType::Dividend => abs((float) $amount),
            TransactionType::Withdrawal => -abs((float) $amount),
        };
    }
}
