<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\PortfolioView\Actions\GetBasketAnalysis;
use App\Contexts\PortfolioView\Actions\GetHoldingTrends;
use App\Contexts\PortfolioView\Datas\BasketAnalysisData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La page liste d'une exposition (`/actions`, `/crypto`…) : l'aperçu en sync, chaque section
 * différée dans son groupe. Le bloc sectoriel montre les secteurs du portefeuille ENTIER, pas de la
 * seule exposition (comportement d'origine, voir `.ai/rules/portfolio-view.md`), et n'existe que
 * sur les expositions qui en ont. La page se rend vide, sans erreur, à un visiteur non connecté.
 */
class AssetClassPage
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetHoldingTrends $trends,
        private BuildEvolutionSeries $evolution,
        private BuildPortfolioPerformances $performances,
        private GetBasketAnalysis $basketAnalysis,
        private GetTransactionJournal $journal,
        private GetSectorBreakdown $sectors,
    ) {}

    public function for(int $userId, AssetClass $exposure): PageProps
    {
        $user = User::query()->find($userId);
        $scope = HoldingScope::ofClasses([$exposure]);

        $deferred = [
            'trends' => new DeferredProp(fn (): array => ($this->trends)($userId, [$exposure]), 'tendances'),
            'evolutionSeries' => new DeferredProp(
                fn () => ($this->evolution)($userId, null, ValuationGranularity::Week, $scope), 'evolution',
            ),
            'performances' => new DeferredProp(fn (): array => ($this->performances)($userId, $scope), 'performances'),
            'basketAnalysis' => new DeferredProp(
                fn () => $user === null ? BasketAnalysisData::empty() : ($this->basketAnalysis)($user, $scope), 'analyse',
            ),
            'transactions' => new DeferredProp(fn (): array => ($this->journal)($userId, $scope), 'transactions'),
        ];

        if ($exposure->hasSectors()) {
            $deferred['sectorBreakdown'] = new DeferredProp(
                fn (): array => $user === null ? [] : ($this->sectors)($user, HoldingScope::all()), 'secteurs',
            );
        }

        return new PageProps(
            sync: [
                'assetClass' => [
                    'key' => $exposure->value,
                    'label' => $exposure->getLabel(),
                    'slug' => $exposure->slug(),
                    'hasSectors' => $exposure->hasSectors(),
                ],
                'overview' => $user === null ? PortfolioOverviewData::empty() : ($this->overview)($user, $scope),
            ],
            deferred: $deferred,
        );
    }
}
