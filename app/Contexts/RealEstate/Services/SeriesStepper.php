<?php

namespace App\Contexts\RealEstate\Services;

use Illuminate\Support\Carbon;

/** La grille hebdomadaire d'une série et les lectures en escalier qu'elle demande. */
class SeriesStepper
{
    /**
     * Tous les lundis depuis celui qui précède `$from`, puis aujourd'hui — sans quoi le dernier
     * point serait vieux de six jours au plus mauvais moment.
     *
     * @return list<string>
     */
    public function weeklyLabels(Carbon $from, Carbon $today): array
    {
        $cursor = $from->copy()->startOfWeek();
        $todayLabel = $today->toDateString();
        $labels = [];

        // Comparaison en jour, pas en horodatage : `$today` porte l'heure courante, et un lundi
        // à 00:00:00 lui serait sinon antérieur, dupliquant le dernier label.
        while ($cursor->toDateString() < $todayLabel) {
            $labels[] = $cursor->toDateString();
            $cursor = $cursor->addWeek();
        }

        $labels[] = $todayLabel;

        return $labels;
    }

    /**
     * Escalier : la dernière valeur de date ≤ au label, zéro avant la première. Aucune
     * interpolation — une valeur n'est connue que le jour où elle a été estimée.
     *
     * @param  list<array{0: string, 1: float}>  $points  Ordre chronologique croissant.
     */
    public function valueAt(array $points, string $label): float
    {
        $value = 0.0;

        foreach ($points as [$date, $amount]) {
            if ($date > $label) {
                break;
            }

            $value = $amount;
        }

        return $value;
    }

    /** @param  array<string, float>  $amountsByKey  Clé comparable au label, mois ou jour. */
    public function sumUpTo(array $amountsByKey, string $label): float
    {
        $total = 0.0;

        foreach ($amountsByKey as $key => $amount) {
            if ($key <= $label) {
                $total += $amount;
            }
        }

        return $total;
    }
}
