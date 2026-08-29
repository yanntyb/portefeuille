<?php

namespace App\Contexts\Income\Services;

use Illuminate\Support\Carbon;

/**
 * La fenêtre des douze derniers mois, glissante au jour : un reçu compte s'il date d'après le même
 * jour l'an dernier. C'est celle de tout le contexte — revenus perçus, dividendes d'un actif.
 *
 * `RealEstate` en emploie une autre, en mois pleins, et la définit chez lui.
 */
class RollingWindow
{
    public function slidingDays(Carbon $today): Carbon
    {
        return $today->copy()->subYear()->startOfDay();
    }
}
