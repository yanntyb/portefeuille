<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Datas\InstrumentDetailData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;

/**
 * Parts actions et crypto de l'instantané hors-ligne : les deux pages liste, et une fiche par
 * position détenue. Les compositions reprennent celles des quatre contrôleurs, props différées
 * comprises — l'instantané les résout toutes, puisqu'il n'a pas d'affichage à ne pas faire
 * attendre.
 *
 * Seule la plage de valorisation par défaut est portée : les autres restent en ligne seulement.
 */
class BuildMarketViewSnapshot
{
    public function __construct(
        private HoldingsPort $holdings,
        private MarketDataPort $market,
        private GetInstrumentDetail $getDetail,
        private GetHoldingTrends $getTrends,
        private GetPortfolioOverview $getOverview,
    ) {}

    /**
     * @return array{
     *     instruments: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>},
     *     crypto: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>},
     * }
     */
    public function __invoke(int $userId): array
    {
        $user = User::query()->find($userId);

        /**
         * `GetPortfolioOverview` et `GetSectorBreakdown` prennent un `User`, pas un identifiant :
         * les quatre contrôleurs les gardent tous derrière un `$user !== null`. Sans cette sortie,
         * un instantané demandé sur une base vide passerait `null` à des paramètres typés.
         */
        if ($user === null) {
            return [
                'instruments' => ['list' => $this->emptyList(forSecurities: true), 'byId' => []],
                'crypto' => ['list' => $this->emptyList(forSecurities: false), 'byId' => []],
            ];
        }

        $securities = [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity];
        $crypto = [AssetClass::Crypto];

        $details = $this->detailsByClass($userId);

        return [
            'instruments' => [
                'list' => [
                    'overview' => ($this->getOverview)($user, $securities),
                    'trends' => ($this->getTrends)($userId, ValuationRange::Max),
                    'performances' => app(BuildPortfolioPerformances::class)($userId, $securities),
                    'evolutionSeries' => app(BuildEvolutionSeries::class)(
                        $userId,
                        null,
                        ValuationGranularity::Week,
                        $securities,
                    ),
                    'sectorBreakdown' => app(GetSectorBreakdown::class)($user),
                    'income' => app(GetIncomeSummary::class)($userId, IncomeSource::Dividend),
                    'annualIncome' => app(GetAnnualIncome::class)($userId, IncomeSource::Dividend),
                ],
                'byId' => $details['securities'],
            ],
            'crypto' => [
                'list' => [
                    'overview' => ($this->getOverview)($user, $crypto),
                    'trends' => ($this->getTrends)($userId, ValuationRange::Max),
                    'performances' => app(BuildPortfolioPerformances::class)($userId, $crypto),
                    'evolutionSeries' => app(BuildEvolutionSeries::class)(
                        $userId,
                        null,
                        ValuationGranularity::Week,
                        $crypto,
                    ),
                ],
                'byId' => $details['crypto'],
            ],
        ];
    }

    /**
     * Page liste sans utilisateur : les mêmes `Data::empty()` que servent les contrôleurs dans ce
     * cas. `$forSecurities` distingue la page Actions, qui porte trois blocs de plus.
     *
     * @return array<string, mixed>
     */
    private function emptyList(bool $forSecurities): array
    {
        $list = [
            'overview' => PortfolioOverviewData::empty(),
            'trends' => [],
            'performances' => [],
            'evolutionSeries' => EvolutionSeriesData::empty(),
        ];

        if (! $forSecurities) {
            return $list;
        }

        return [
            ...$list,
            'sectorBreakdown' => [],
            'income' => IncomeSummaryData::empty(),
            'annualIncome' => [],
        ];
    }

    /**
     * Les fiches, réparties selon la classe que porte l'actif lui-même. `InstrumentType::isCrypto()`
     * est la seule définition de ce partage : le recopier ici ferait diverger l'instantané des deux
     * contrôleurs de fiche, qui renvoient 404 sur l'actif de l'autre classe.
     *
     * @return array{securities: array<int, array<string, mixed>>, crypto: array<int, array<string, mixed>>}
     */
    private function detailsByClass(int $userId): array
    {
        $byClass = ['securities' => [], 'crypto' => []];

        foreach ($this->holdings->holdingsFor($userId) as $holding) {
            /** @var HoldingSnapshotData $holding */
            $detail = ($this->getDetail)($userId, $holding->assetId);

            if ($detail === null) {
                continue;
            }

            $byClass[$detail->type->isCrypto() ? 'crypto' : 'securities'][$holding->assetId]
                = $this->page($userId, $holding->assetId, $detail);
        }

        return $byClass;
    }

    /** @return array<string, mixed> */
    private function page(int $userId, int $assetId, InstrumentDetailData $detail): array
    {
        $page = [
            'instrument' => $detail,
            'performances' => app(BuildAssetPerformances::class)($userId, $assetId),
            'priceHistory' => $this->market->priceHistory($assetId, Carbon::now()->subMonths(12)),
            'valuation' => app(BuildAssetValuationSeries::class)(
                $userId,
                $assetId,
                ValuationRange::Max,
                ValuationGranularity::Week,
            ),
        ];

        /** La fiche crypto n'affiche pas de dividendes : les porter ici gonflerait le blob pour rien. */
        if (! $detail->type->isCrypto()) {
            $page['dividends'] = app(GetAssetDividendHistory::class)($userId, $assetId);
        }

        return $page;
    }
}
