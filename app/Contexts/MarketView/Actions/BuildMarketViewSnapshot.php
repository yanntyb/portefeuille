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
 * Instantané hors-ligne du volet marché : une page liste par exposition, et une fiche par
 * position détenue. Les compositions reprennent celles d'`AssetClassController` et des deux
 * contrôleurs de fiche, props différées comprises — l'instantané les résout toutes, puisqu'il n'a
 * pas d'affichage à ne pas faire attendre.
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
     *     classes: array<string, array<string, mixed>>,
     *     assets: array<int, array<string, mixed>>,
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
            return ['classes' => $this->emptyClasses(), 'assets' => []];
        }

        $classes = [];

        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->listFor($user, $userId, $exposure);
        }

        return ['classes' => $classes, 'assets' => $this->pagesFor($userId)];
    }

    /**
     * La composition d'une page liste, gates comprises. Les mêmes que celles d'`AssetClassController` :
     * l'instantané doit porter ce que la page affiche, jamais une composition parallèle.
     *
     * @return array<string, mixed>
     */
    private function listFor(User $user, int $userId, AssetClass $exposure): array
    {
        $classes = [$exposure];

        $list = [
            'overview' => ($this->getOverview)($user, $classes),
            'trends' => ($this->getTrends)($userId, ValuationRange::Max, $classes),
            'performances' => app(BuildPortfolioPerformances::class)($userId, $classes),
            'evolutionSeries' => app(BuildEvolutionSeries::class)(
                $userId,
                null,
                ValuationGranularity::Week,
                $classes,
            ),
        ];

        if ($exposure->hasSectors()) {
            $list['sectorBreakdown'] = app(GetSectorBreakdown::class)($user);
        }

        $source = IncomeSource::forAssetClass($exposure);

        if ($source !== null) {
            $list['income'] = app(GetIncomeSummary::class)($userId, $source);
            $list['annualIncome'] = app(GetAnnualIncome::class)($userId, $source);
        }

        return $list;
    }

    /**
     * Les fiches, une par position détenue. Plus de partage par classe : `/asset/{id}` sert le
     * même contenu à tout actif, quelle que soit son exposition.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pagesFor(int $userId): array
    {
        $pages = [];

        foreach ($this->holdings->holdingsFor($userId) as $holding) {
            /** @var HoldingSnapshotData $holding */
            $detail = ($this->getDetail)($userId, $holding->assetId);

            if ($detail === null) {
                continue;
            }

            $pages[$holding->assetId] = $this->page($userId, $holding->assetId, $detail);
        }

        return $pages;
    }

    /**
     * Les quatre listes d'une base sans utilisateur : les mêmes `Data::empty()` que sert le
     * contrôleur, sous les mêmes gates.
     *
     * @return array<string, array<string, mixed>>
     */
    private function emptyClasses(): array
    {
        $classes = [];

        foreach (AssetClass::cases() as $exposure) {
            $list = [
                'overview' => PortfolioOverviewData::empty(),
                'trends' => [],
                'performances' => [],
                'evolutionSeries' => EvolutionSeriesData::empty(),
            ];

            if ($exposure->hasSectors()) {
                $list['sectorBreakdown'] = [];
            }

            if (IncomeSource::forAssetClass($exposure) !== null) {
                $list['income'] = IncomeSummaryData::empty();
                $list['annualIncome'] = [];
            }

            $classes[$exposure->value] = $list;
        }

        return $classes;
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

        /** Une exposition qui ne distribue rien n'a pas de détachements : les porter gonflerait le blob pour rien. */
        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $page['dividends'] = app(GetAssetDividendHistory::class)($userId, $assetId);
        }

        return $page;
    }
}
