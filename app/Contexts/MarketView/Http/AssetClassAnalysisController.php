<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page analyse d'une exposition : performances et secteurs, rien d'autre. Aucune prop
 * synchrone, à la différence de la page liste : tout ce qu'elle montre demande un calcul, et rien
 * n'a besoin d'être là au premier rendu.
 */
class AssetClassAnalysisController
{
    public function __construct(
        private ValuationPort $valuation,
        private SectorBreakdownPort $sectors,
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
            ],
            'performances' => Inertia::defer(
                fn () => $this->valuation->performancesFor($userId, $exposure), 'performances',
            ),
        ];

        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(
                fn () => $this->sectors->breakdownFor($userId), 'secteurs',
            );
        }

        return Inertia::render('AssetClass/Analysis', $props);
    }
}
