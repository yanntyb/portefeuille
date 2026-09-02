<?php

namespace App\Contexts\PortfolioView\Services;

use Illuminate\Support\Carbon;

/**
 * Profondeur de cours servie à la fiche instrument. Sa mini-timeline ne peut révéler que ce
 * qu'elle reçoit, et son plancher d'un an figerait une plage de douze mois sur elle-même.
 *
 * La borne est partagée par la fiche en ligne et l'instantané hors-ligne : deux profondeurs
 * feraient raconter au même graphe deux histoires selon le réseau.
 */
class PriceHistoryWindow
{
    /** Cinq ans : de quoi dérouler, sans laisser le blob hors-ligne grossir avec les années. */
    public const MONTHS = 60;

    public static function since(): Carbon
    {
        return Carbon::now()->subMonths(self::MONTHS);
    }
}
