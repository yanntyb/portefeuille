<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page analyse d'une exposition. Aucune prop synchrone, à la différence de la page liste :
 * tout ce qu'elle montre demande un calcul, et rien n'a besoin d'être là au premier rendu.
 */
class AssetClassAnalysisController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
    ) {}

    public function __invoke(): Response
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        return Inertia::render('AssetClass/Analysis', [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'slug' => $exposure->slug(),
            ],
            /** Un seul groupe : les trois analyses viennent des mêmes lectures mémoïsées. */
            'analysis' => Inertia::defer(
                fn () => $this->overview->analysisFor($userId, $exposure), 'analyses',
            ),
            'drawdown' => Inertia::defer(
                fn () => $this->valuation->drawdownFor($userId, $exposure), 'analyses',
            ),
        ]);
    }
}
