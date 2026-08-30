<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Le poids d'une position dans son portefeuille, en pourcentage. Le pendant à une ligne de
 * `Concentration`, qui mesure le portefeuille entier là où celui-ci répond pour une seule ligne :
 * de combien elle pèse, donc de combien la renforcer déplacerait l'ensemble.
 *
 * Même règle d'exclusion que `Concentration` : une position sans cours connu, nulle ou négative
 * n'est pas une exposition à mesurer.
 */
class PositionWeight
{
    /** @param  list<?float>  $values  Valeurs de marché de toutes les positions, celle-ci comprise. */
    public function of(?float $value, array $values): ?float
    {
        if ($value === null || $value <= 0.0) {
            return null;
        }

        $total = array_sum(array_filter(
            $values,
            fn (?float $candidate): bool => $candidate !== null && $candidate > 0.0,
        ));

        return $total > 0.0 ? $value / $total * 100 : null;
    }
}
