<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Actions\GetHoldingTrends;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page crypto, calquée sur celle des titres. Deux sections en moins : la crypto ne verse pas de
 * dividende et n'a pas de secteur d'activité — leurs sections n'auraient rien à montrer.
 */
class CryptoController
{
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetHoldingTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $range = ValuationRange::fromRequest(request()->query('range'));
        $types = [InstrumentType::Crypto];

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user, $types)
            : PortfolioOverviewData::empty();

        return Inertia::render('Crypto/Index', [
            'overview' => $overview,
            /**
             * Un groupe par section, comme sur la page Actions : chaque squelette se remplit à son
             * rythme au lieu d'attendre le plus lent de la page.
             */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($user?->id ?? 0, $range), 'tendances'),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id, $types)
                : [], 'performances'),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Week, $types)
                : EvolutionSeriesData::empty(), 'evolution'),
        ]);
    }
}
