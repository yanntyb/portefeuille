<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Actions\GetHoldingTrends;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page liste d'une exposition. Une seule composition sert les quatre : elles ne diffèrent que
 * par deux sections, et les recopier une fois par classe les ferait diverger à la première
 * correction. L'exposition arrive par le défaut de route, posé à la déclaration.
 */
class AssetClassController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private GetHoldingTrends $getTrends,
        private SectorBreakdownPort $sectors,
        private IncomePort $income,
        private ValuationPort $valuation,
    ) {}

    public function __invoke(): Response
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        /**
         * Les deux drapeaux voyagent avec la classe : la page ne peut pas déduire d'une valeur
         * absente qu'une section n'existe pas. `aheadOfNetwork` rend `null`, jamais `undefined`,
         * donc tester la valeur ferait apparaître les sections sur toutes les expositions.
         */
        $props = [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'hasSectors' => $exposure->hasSectors(),
                'hasIncome' => $this->income->supportsExposure($exposure),
            ],
            'overview' => $this->overview->overviewFor($userId, $exposure),
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, [$exposure]), 'tendances'),
            'performances' => Inertia::defer(
                fn () => $this->valuation->performancesFor($userId, $exposure), 'performances',
            ),
            'evolutionSeries' => Inertia::defer(
                fn () => $this->valuation->evolutionFor($userId, $exposure), 'evolution',
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

        return Inertia::render('AssetClass/Index', $props);
    }
}
