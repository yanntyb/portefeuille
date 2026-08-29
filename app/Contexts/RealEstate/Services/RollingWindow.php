<?php

namespace App\Contexts\RealEstate\Services;

use Illuminate\Support\Carbon;

/**
 * La fenêtre des douze derniers mois, comptée en mois d'échéance : du premier jour du mois d'il y
 * a onze mois à aujourd'hui. C'est celle de tout le contexte — loyers, charges, échéances.
 *
 * `Income` en emploie une autre, glissante au jour, et la définit chez lui : les deux ne se
 * croisent jamais.
 */
class RollingWindow
{
    public function monthsFull(Carbon $today): string
    {
        return $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();
    }
}
