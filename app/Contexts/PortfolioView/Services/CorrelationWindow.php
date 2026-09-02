<?php

namespace App\Contexts\PortfolioView\Services;

use Illuminate\Support\Carbon;

/**
 * Profondeur lue par la matrice de corrélations. Un an : au-delà, une corrélation raconte un
 * régime de marché révolu, et la matrice se lit pour décider aujourd'hui.
 *
 * Distincte de `PriceHistoryWindow`, qui sert l'indice de la poche : une chute mémorable a besoin
 * d'années quand une corrélation vieillit vite.
 */
class CorrelationWindow
{
    public const MONTHS = 12;

    public static function since(): Carbon
    {
        return Carbon::now()->subMonths(self::MONTHS);
    }
}
