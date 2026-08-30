<?php

namespace App\Contexts\Portfolio\Services;

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
}
