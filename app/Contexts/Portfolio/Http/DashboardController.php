<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetCatalogTrends;
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
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetCatalogTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $range = ValuationRange::fromRequest(request()->query('range'));

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        return Inertia::render('Dashboard', [
            'overview' => $overview,
            /**
             * Un groupe par section : Inertia résout un groupe par requête, donc chaque squelette
             * se remplit à son rythme au lieu d'attendre le plus lent de la page.
             */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($range), 'catalogue'),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : [], 'performances'),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Week)
                : EvolutionSeriesData::empty(), 'evolution'),
            'sectorBreakdown' => Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : [], 'secteurs'),
        ]);
    }
}
