<?php

namespace App\Contexts\PortfolioView\Services;

use App\Contexts\Valuation\Enums\ValuationGranularity;
use Illuminate\Support\Carbon;

/**
 * Le pas du graphe d'un actif : quotidien tant que l'historique tient dans un trimestre,
 * hebdomadaire au-delà. Une décision de rendu, qui ne traverse pas Valuation.
 */
class ChartStep
{
    public const DAILY_STEP_MAX_DAYS = 92;

    /** @param  list<string>  $labels dates `Y-m-d`, croissantes */
    public function for(array $labels): ValuationGranularity
    {
        if ($labels === []) {
            return ValuationGranularity::Day;
        }

        $span = Carbon::parse($labels[0])->diffInDays(Carbon::parse($labels[count($labels) - 1]));

        return $span > self::DAILY_STEP_MAX_DAYS
            ? ValuationGranularity::Week
            : ValuationGranularity::Day;
    }
}
