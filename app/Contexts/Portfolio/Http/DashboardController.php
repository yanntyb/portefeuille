<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetCatalogTrends;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
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
        private GetInstrumentCatalog $getCatalog,
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
            /** Le catalogue est différé : sans recherche, la liste se contente des positions déjà servies. */
            'catalog' => Inertia::defer(fn () => ($this->getCatalog)($user?->id ?? 0)),
            'catalogRange' => $range->value,
            'trends' => Inertia::defer(fn () => ($this->getTrends)($range)),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : []),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Week)
                : EvolutionSeriesData::empty()),
            'sectorBreakdown' => Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : []),
        ]);
    }
}
