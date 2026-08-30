<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Actions\GetHoldingTrends;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page liste d'une exposition. Une seule composition sert les quatre : elles ne diffèrent que
 * par leurs données, jamais par leur forme. L'exposition arrive par le défaut de route, posé à la
 * déclaration. Secteurs, performances et revenus se lisent désormais sur la page analyse
 * (`AssetClassAnalysisController`).
 */
class AssetClassController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private GetHoldingTrends $getTrends,
        private ValuationPort $valuation,
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
            ],
            'overview' => $this->overview->overviewFor($userId, $exposure),
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, [$exposure]), 'tendances'),
            'evolutionSeries' => Inertia::defer(
                fn () => $this->valuation->evolutionFor($userId, $exposure), 'evolution',
            ),
        ];

        return Inertia::render('AssetClass/Index', $props);
    }
}
