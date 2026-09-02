<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Actions\GetHoldingTrends;
use App\Contexts\PortfolioView\Ports\BasketAnalysisPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page d'une exposition. Une seule composition sert les quatre : elles ne diffèrent que par
 * leurs données, jamais par leur forme. L'exposition arrive par le défaut de route, posé à la
 * déclaration. Performances et secteurs, un temps servis par une page analyse séparée, sont
 * revenus ici en sections repliées : leur calcul reste différé, section par section.
 */
class AssetClassController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private GetHoldingTrends $getTrends,
        private ValuationPort $valuation,
        private SectorBreakdownPort $sectors,
        private BasketAnalysisPort $basketAnalysis,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(): Response
    {
        $userId = auth()->id() ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));
        $scope = HoldingScope::ofClasses([$exposure]);

        $props = [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'slug' => $exposure->slug(),
                'hasSectors' => $exposure->hasSectors(),
            ],
            'overview' => $this->overview->overviewFor($userId, $scope),
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, [$exposure]), 'tendances'),
            'evolutionSeries' => Inertia::defer(
                fn () => $this->valuation->evolutionFor($userId, $scope), 'evolution',
            ),
            'performances' => Inertia::defer(
                fn () => $this->valuation->performancesFor($userId, $scope), 'performances',
            ),
            'basketAnalysis' => Inertia::defer(
                fn () => $this->basketAnalysis->analysisFor($userId, $scope), 'analyse',
            ),
            /**
             * L'historique entier de la poche, sans fenêtre : la section reste repliée, et son
             * groupe ne part qu'au dépli.
             */
            'transactions' => Inertia::defer(
                fn () => $this->transactions->transactionsForScope($userId, $scope), 'transactions',
            ),
        ];

        /**
         * Une exposition sans secteur n'a rien à ventiler : la prop n'est pas servie du tout.
         *
         * Le périmètre y est délibérément large — les secteurs affichés sont ceux du portefeuille
         * entier, pas ceux de la poche. C'est le comportement d'origine de cette page, et le
         * resserrer serait un changement d'affichage, pas une conséquence du chantier.
         */
        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(
                fn () => $this->sectors->breakdownFor($userId, HoldingScope::all()), 'secteurs',
            );
        }

        return Inertia::render('AssetClass/Index', $props);
    }
}
