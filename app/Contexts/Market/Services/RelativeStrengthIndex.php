<?php

namespace App\Contexts\Market\Services;

/**
 * L'indice de force relative de Wilder : la vigueur des hausses face aux baisses sur une fenêtre
 * de séances, ramenée entre 0 et 100.
 *
 * Le lissage est celui de Wilder et non une moyenne simple glissante : la première moyenne porte
 * sur `$period` variations, les suivantes pèsent la moyenne précédente `$period - 1` fois contre
 * une fois la nouvelle variation. Les deux méthodes rendent des chiffres proches, ce qui rend
 * l'erreur silencieuse — d'où le cas de référence dans le test.
 *
 * Sans aucune baisse sur la fenêtre, l'indice vaut 100 : la division par des pertes nulles n'a pas
 * de sens, et une série qui ne recule jamais est bien à son maximum de force.
 */
class RelativeStrengthIndex
{
    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes, int $period = 14): ?float
    {
        if ($period <= 0 || count($closes) < $period + 1) {
            return null;
        }

        $gains = [];
        $losses = [];

        for ($index = 1; $index < count($closes); $index++) {
            $change = $closes[$index] - $closes[$index - 1];
            $gains[] = max($change, 0.0);
            $losses[] = max(-$change, 0.0);
        }

        $averageGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $averageLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($index = $period; $index < count($gains); $index++) {
            $averageGain = ($averageGain * ($period - 1) + $gains[$index]) / $period;
            $averageLoss = ($averageLoss * ($period - 1) + $losses[$index]) / $period;
        }

        if ($averageLoss <= 0.0) {
            return 100.0;
        }

        return 100 - 100 / (1 + $averageGain / $averageLoss);
    }
}
