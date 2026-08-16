<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /** Profondeur initiale du graphe d'évolution, prolongée au scroll par pas de six mois. */
    private const DEFAULT_EVOLUTION_MONTHS = 6;

    public function __construct(private GetPortfolioOverview $getPortfolioOverview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        $range = ValuationRange::fromRequest(request()->query('range'));
        $granularity = ValuationGranularity::fromRequest(request()->query('granularity'));
        $months = $this->evolutionMonths();

        return Inertia::render('Dashboard', [
            'overview' => $overview,
            'valuationRange' => $range->value,
            'valuationGranularity' => $granularity->value,
            'valuationMonths' => $months,
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : []),
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, $months, ValuationGranularity::Day)
                : EvolutionSeriesData::empty()),
            'sectorBreakdown' => Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : []),
        ]);
    }

    private function evolutionMonths(): int
    {
        $requested = (int) request()->query('months', (string) self::DEFAULT_EVOLUTION_MONTHS);

        return $requested > 0 ? $requested : self::DEFAULT_EVOLUTION_MONTHS;
    }
}
