<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\FiftyTwoWeekData;

/**
 * Le plus-haut et le plus-bas de l'année boursière, comptés en séances et non en mois : une année
 * civile n'a pas le même nombre de jours cotés selon les places et les fériés, et la fenêtre
 * doit rester la même d'un instrument à l'autre.
 *
 * Une série plus courte que la fenêtre est lue en entier plutôt que rejetée : un instrument coté
 * depuis six mois a bien un plus-haut, simplement plus jeune.
 */
class FiftyTwoWeekRange
{
    /** Cinquante-deux semaines cotées, à cinq séances la semaine, fériés déduits. */
    public const SESSIONS = 252;

    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes): ?FiftyTwoWeekData
    {
        if ($closes === []) {
            return null;
        }

        $window = array_slice($closes, -self::SESSIONS);
        $high = max($window);
        $last = $window[count($window) - 1];

        return new FiftyTwoWeekData(
            high: $high,
            low: min($window),
            gapPct: $high > 0.0 ? ($last - $high) / $high * 100 : null,
        );
    }
}
