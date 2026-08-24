<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Actions\GetHoldingTrends;
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

/**
 * La page liste d'une exposition. Une seule composition sert les quatre : elles ne diffèrent que
 * par deux sections, et les recopier une fois par classe les ferait diverger à la première
 * correction. L'exposition arrive par le défaut de route, posé à la déclaration.
 */
class AssetClassController
{
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetHoldingTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));
        $range = ValuationRange::fromRequest(request()->query('range'));
        $classes = [$exposure];

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user, $classes)
            : PortfolioOverviewData::empty();

        $source = IncomeSource::forAssetClass($exposure);

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
                'hasIncome' => $source !== null,
            ],
            'overview' => $overview,
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, $range, $classes), 'tendances'),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($userId, $classes)
                : [], 'performances'),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($userId, null, ValuationGranularity::Week, $classes)
                : EvolutionSeriesData::empty(), 'evolution'),
        ];

        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : [], 'secteurs');
        }

        if ($source !== null) {
            /** Un seul groupe pour les deux : la section les affiche ensemble. */
            $props['income'] = Inertia::defer(fn () => $user !== null
                ? app(GetIncomeSummary::class)($userId, $source)
                : IncomeSummaryData::empty(), 'revenus');
            $props['annualIncome'] = Inertia::defer(fn () => $user !== null
                ? app(GetAnnualIncome::class)($userId, $source)
                : [], 'revenus');
        }

        return Inertia::render('AssetClass/Index', $props);
    }
}
