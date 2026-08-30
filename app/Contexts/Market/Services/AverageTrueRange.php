<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\AtrData;
use App\Contexts\Market\Datas\TrueRangeBar;

/**
 * L'amplitude vraie moyenne de Wilder : de combien bouge une séance ordinaire. L'amplitude vraie
 * d'une séance est le plus grand des trois écarts — son propre plus-haut à son plus-bas, et chacun
 * d'eux à la clôture de la veille — ce qui compte les trous d'ouverture qu'un simple haut-bas
 * manquerait.
 *
 * Même lissage que le RSI : moyenne simple sur la première fenêtre, puis pondération de Wilder.
 */
class AverageTrueRange
{
    /** @param  list<TrueRangeBar>  $bars  Séances dans l'ordre chronologique. */
    public function of(array $bars, int $period = 14): ?AtrData
    {
        if ($period <= 0 || count($bars) < $period + 1) {
            return null;
        }

        $ranges = [];

        for ($index = 1; $index < count($bars); $index++) {
            $bar = $bars[$index];
            $previousClose = $bars[$index - 1]->close;

            $ranges[] = max(
                $bar->high - $bar->low,
                abs($bar->high - $previousClose),
                abs($bar->low - $previousClose),
            );
        }

        $average = array_sum(array_slice($ranges, 0, $period)) / $period;

        for ($index = $period; $index < count($ranges); $index++) {
            $average = ($average * ($period - 1) + $ranges[$index]) / $period;
        }

        $lastClose = $bars[count($bars) - 1]->close;

        return new AtrData(
            value: $average,
            percent: $lastClose > 0.0 ? $average * 100 / $lastClose : null,
        );
    }
}
