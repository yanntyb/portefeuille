<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page analyse d'une exposition. Aucune prop synchrone, à la différence de la page liste :
 * tout ce qu'elle montre demande un calcul, et rien n'a besoin d'être là au premier rendu.
 *
 * Secteurs, performances et revenus viennent ici depuis la page liste, telles quelles : mêmes
 * ports, mêmes groupes différés, seul le composant Inertia rendu change.
 */
class AssetClassAnalysisController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
        private SectorBreakdownPort $sectors,
        private IncomePort $income,
    ) {}

    public function __invoke(): Response
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        $props = [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'slug' => $exposure->slug(),
                'hasSectors' => $exposure->hasSectors(),
                'hasIncome' => $this->income->supportsExposure($exposure),
            ],
            /** Un seul groupe : les trois analyses viennent des mêmes lectures mémoïsées. */
            'analysis' => Inertia::defer(
                fn () => $this->overview->analysisFor($userId, $exposure), 'analyses',
            ),
            'drawdown' => Inertia::defer(
                fn () => $this->valuation->drawdownFor($userId, $exposure), 'analyses',
            ),
            'performances' => Inertia::defer(
                fn () => $this->valuation->performancesFor($userId, $exposure), 'performances',
            ),
        ];

        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(
                fn () => $this->sectors->breakdownFor($userId), 'secteurs',
            );
        }

        if ($this->income->supportsExposure($exposure)) {
            /** Un seul groupe pour les deux : la section les affiche ensemble. */
            $props['income'] = Inertia::defer(
                fn () => $this->income->summaryFor($userId, $exposure), 'revenus',
            );
            $props['annualIncome'] = Inertia::defer(
                fn () => $this->income->annualFor($userId, $exposure), 'revenus',
            );
        }

        return Inertia::render('AssetClass/Analysis', $props);
    }
}
