<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\DrawdownData;
use InvalidArgumentException;

/**
 * La perte maximale depuis un plus-haut, en une passe. Mesure le risque vécu, et non la
 * volatilité : c'est ce qu'un porteur a réellement encaissé avant de se refaire.
 */
class Drawdown
{
    /**
     * @param  list<string>  $labels  Même longueur et même ordre que `$values`.
     * @param  list<float>  $values
     *
     * @throws InvalidArgumentException si `$labels` et `$values` n'ont pas la même longueur.
     *
     * Le drawdown courant se rapporte au maximum courant de la série. Un sommet inférieur au
     * plus-haut historique ne rouvre pas de référence : le porteur encaisse toujours depuis
     * le plus-haut atteint avant de se refaire. Sur un plateau au fond, `troughLabel` désigne
     * le premier point atteignant le minimum.
     */
    public function of(array $labels, array $values): DrawdownData
    {
        if (count($labels) !== count($values)) {
            throw new InvalidArgumentException(
                'labels et values doivent avoir la même longueur (labels: '.count($labels).', values: '.count($values).')'
            );
        }

        $peak = null;
        $peakLabel = null;
        $maxDepth = null;
        $maxPeakLabel = null;
        $maxTroughLabel = null;
        $currentDepth = null;

        foreach ($values as $index => $value) {
            /** Tant que rien ne vaut, il n'y a pas de plus-haut auquel rapporter une chute. */
            if ($value <= 0.0) {
                continue;
            }

            if ($peak === null || $value >= $peak) {
                $peak = $value;
                $peakLabel = $labels[$index];
            }

            $depth = ($peak - $value) / $peak * 100;
            $currentDepth = $depth;
            $maxDepth ??= 0.0;

            if ($depth > $maxDepth) {
                $maxDepth = $depth;
                $maxPeakLabel = $peakLabel;
                $maxTroughLabel = $labels[$index];
            }
        }

        if ($maxDepth === null) {
            return DrawdownData::empty();
        }

        return new DrawdownData(
            maxDepth: $maxDepth,
            peakLabel: $maxPeakLabel,
            troughLabel: $maxTroughLabel,
            currentDepth: $currentDepth,
        );
    }
}
