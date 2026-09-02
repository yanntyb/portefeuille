<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\HoldingSnapshotData;
use App\Contexts\PortfolioView\Datas\InstrumentDetailData;
use App\Contexts\PortfolioView\Ports\BasketAnalysisPort;
use App\Contexts\PortfolioView\Ports\HoldingsPort;
use App\Contexts\PortfolioView\Ports\IncomePort;
use App\Contexts\PortfolioView\Ports\InstrumentAnalysisPort;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use App\Contexts\PortfolioView\Services\PriceHistoryWindow;

/**
 * Instantané hors-ligne du volet portefeuille : une page par exposition, et une fiche par position
 * détenue. Les compositions reprennent celles d'`AssetClassController` et du contrôleur de
 * fiche, props différées comprises — l'instantané les résout toutes, puisqu'il n'a
 * pas d'affichage à ne pas faire attendre.
 *
 * Seule la plage de valorisation par défaut est portée : les autres restent en ligne seulement.
 */
class BuildPortfolioViewSnapshot
{
    public function __construct(
        private HoldingsPort $holdings,
        private MarketDataPort $market,
        private GetInstrumentDetail $getDetail,
        private GetHoldingTrends $getTrends,
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
        private SectorBreakdownPort $sectors,
        private IncomePort $income,
        private InstrumentAnalysisPort $analysis,
        private BasketAnalysisPort $basketAnalysis,
        private TransactionsPort $transactions,
    ) {}

    /**
     * @return array{
     *     classes: array<string, array<string, mixed>>,
     *     assets: array<int, array<string, mixed>>,
     * }
     */
    public function __invoke(int $userId): array
    {
        $classes = [];

        /**
         * Aucune sortie anticipée sur une base sans utilisateur : les ports rendent déjà des
         * listes et des totaux vides pour un identifiant inconnu, et la composition en sort
         * identique — un chemin dédié n'aurait fait que la recopier.
         */
        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->listFor($userId, $exposure);
        }

        return ['classes' => $classes, 'assets' => $this->pagesFor($userId)];
    }

    /**
     * La composition d'une page d'exposition, gates comprises. La même que celle
     * d'`AssetClassController` : l'instantané doit porter ce que la page affiche, jamais une
     * composition parallèle.
     *
     * @return array<string, mixed>
     */
    private function listFor(int $userId, AssetClass $exposure): array
    {
        $scope = HoldingScope::ofClasses([$exposure]);

        $page = [
            'overview' => $this->overview->overviewFor($userId, $scope),
            'trends' => ($this->getTrends)($userId, [$exposure]),
            'evolutionSeries' => $this->valuation->evolutionFor($userId, $scope),
            'performances' => $this->valuation->performancesFor($userId, $scope),
            'basketAnalysis' => $this->basketAnalysis->analysisFor($userId, $scope),
            'transactions' => $this->transactions->transactionsForScope($userId, $scope),
        ];

        if ($exposure->hasSectors()) {
            $page['sectorBreakdown'] = $this->sectors->breakdownFor($userId, HoldingScope::all());
        }

        return $page;
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

    /** @return array<string, mixed> */
    private function page(int $userId, int $assetId, InstrumentDetailData $detail): array
    {
        $page = [
            'instrument' => $detail,
            'performances' => $this->valuation->assetPerformancesFor($userId, $assetId),
            'priceHistory' => $this->market->priceHistory($assetId, PriceHistoryWindow::since()),
            'valuation' => $this->valuation->assetSeriesFor($userId, $assetId),
            'analysis' => $this->analysis->forAsset($userId, $assetId),
        ];

        /** Une exposition qui ne distribue rien n'a pas de détachements : les porter gonflerait le blob pour rien. */
        if ($this->income->supportsExposure($detail->assetClass)) {
            $page['dividends'] = $this->income->assetHistoryFor($userId, $assetId);
        }

        return $page;
    }
}
