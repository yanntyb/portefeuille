<?php

namespace App\Contexts\RealEstate\Services;

/**
 * Ce qu'une fenêtre retient d'une suite de montants datés. Loyers encaissés, charges payées et
 * échéances réglées y passent tous les trois : c'est le même filtre suivi de la même somme.
 */
class PropertyWindowTotals
{
    /** @param  list<array{month: string, amount: float}>  $amountsByMonth */
    public function within(array $amountsByMonth, string $from, string $to): float
    {
        $total = 0.0;

        foreach ($amountsByMonth as $entry) {
            if ($entry['month'] >= $from && $entry['month'] <= $to) {
                $total += $entry['amount'];
            }
        }

        return $total;
    }
}
