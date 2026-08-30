<?php

namespace App\Contexts\Market\Services;

/**
 * L'écart d'un cours à une référence, en points de pourcentage signés. Deux lignes de la fiche
 * le demandent — l'écart au prix de revient et l'écart à la moyenne mobile — et l'adaptateur qui
 * les compose n'a pas à savoir diviser.
 */
class PriceGap
{
    public function pct(?float $reference, ?float $price): ?float
    {
        if ($reference === null || $price === null || $reference <= 0.0) {
            return null;
        }

        return ($price - $reference) / $reference * 100;
    }
}
