<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Actions\GetHoldingTrends;
use App\Contexts\PortfolioView\Ports\ClassAnalysisPort;
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
        private ClassAnalysisPort $classAnalysis,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(): Response
    {
        $userId = auth()->id() ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        $props = [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'slug' => $exposure->slug(),
                'hasSectors' => $exposure->hasSectors(),
            ],
            'overview' => $this->overview->overviewFor($userId, $exposure),
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, [$exposure]), 'tendances'),
            'evolutionSeries' => Inertia::defer(
                fn () => $this->valuation->evolutionFor($userId, $exposure), 'evolution',
            ),
            'performances' => Inertia::defer(
                fn () => $this->valuation->performancesFor($userId, $exposure), 'performances',
            ),
            'classAnalysis' => Inertia::defer(
                fn () => $this->classAnalysis->forClass($userId, $exposure), 'analyse',
            ),
            /**
             * L'historique entier de la poche, sans fenêtre : la section reste repliée, et son
             * groupe ne part qu'au dépli.
             */
            'transactions' => Inertia::defer(
                fn () => $this->transactions->transactionsForClass($userId, $exposure), 'transactions',
            ),
        ];

        /** Une exposition sans secteur n'a rien à ventiler : la prop n'est pas servie du tout. */
        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(
                fn () => $this->sectors->breakdownFor($userId), 'secteurs',
            );
        }

        return Inertia::render('AssetClass/Index', $props);
    }
}
