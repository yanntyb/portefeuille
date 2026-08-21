<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\InstrumentView\Actions\GetHoldingTrends;
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

class InstrumentsController
{
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetHoldingTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $range = ValuationRange::fromRequest(request()->query('range'));

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        return Inertia::render('Instruments/Index', [
            'overview' => $overview,
            /**
             * Un groupe par section : Inertia résout un groupe par requête, donc chaque squelette
             * se remplit à son rythme au lieu d'attendre le plus lent de la page.
             */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($user?->id ?? 0, $range), 'tendances'),
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
            /** Un seul groupe pour les deux : la section les affiche ensemble. */
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetIncomeSummary::class)($user->id, IncomeSource::Dividend)
                : IncomeSummaryData::empty(), 'revenus'),
            'annualIncome' => Inertia::defer(fn () => $user !== null
                ? app(GetAnnualIncome::class)($user->id, IncomeSource::Dividend)
                : [], 'revenus'),
        ]);
    }
}
