<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\DrawdownData;

/**
 * La perte maximale depuis un plus-haut, en une passe. Mesure le risque vécu, et non la
 * volatilité : c'est ce qu'un porteur a réellement encaissé avant de se refaire.
 */
class Drawdown
{
    /**
     * @param  list<string>  $labels  Même longueur et même ordre que `$values`.
     * @param  list<float>  $values
     */
    public function of(array $labels, array $values): DrawdownData
    {
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
