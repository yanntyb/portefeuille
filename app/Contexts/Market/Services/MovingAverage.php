<?php

namespace App\Contexts\Market\Services;

/**
 * La moyenne mobile simple des dernières clôtures : la tendance de fond, débarrassée du bruit
 * quotidien. Rend `null` plutôt que zéro sur une série trop courte — un instrument jeune n'a pas
 * de tendance longue, et zéro se lirait comme un cours.
 */
class MovingAverage
{
    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes, int $period): ?float
    {
        if ($period <= 0 || count($closes) < $period) {
            return null;
        }

        return array_sum(array_slice($closes, -$period)) / $period;
    }
}
