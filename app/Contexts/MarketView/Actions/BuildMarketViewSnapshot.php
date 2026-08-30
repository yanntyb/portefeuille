<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Datas\InstrumentDetailData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Contexts\MarketView\Services\PriceHistoryWindow;

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
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
        private SectorBreakdownPort $sectors,
        private IncomePort $income,
    ) {}

    /**
     * @return array{
     *     classes: array<string, array<string, mixed>>,
     *     analyses: array<string, array<string, mixed>>,
     *     assets: array<int, array<string, mixed>>,
     * }
     */
    public function __invoke(int $userId): array
    {
        $classes = [];
        $analyses = [];

        /**
         * Aucune sortie anticipée sur une base sans utilisateur : les ports rendent déjà des
         * listes et des totaux vides pour un identifiant inconnu, et la composition en sort
         * identique — un chemin dédié n'aurait fait que la recopier.
         */
        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->listFor($userId, $exposure);
            $analyses[$exposure->value] = $this->analysisFor($userId, $exposure);
        }

        return ['classes' => $classes, 'analyses' => $analyses, 'assets' => $this->pagesFor($userId)];
    }

    /**
     * La composition d'une page liste. La même que celle d'`AssetClassController` :
     * l'instantané doit porter ce que la page affiche, jamais une composition parallèle.
     *
     * @return array<string, mixed>
     */
    private function listFor(int $userId, AssetClass $exposure): array
    {
        return [
            'overview' => $this->overview->overviewFor($userId, $exposure),
            'trends' => ($this->getTrends)($userId, [$exposure]),
            'evolutionSeries' => $this->valuation->evolutionFor($userId, $exposure),
        ];
    }

    /**
     * La composition d'une page analyse, gates comprises. Les mêmes que celles
     * d'`AssetClassAnalysisController` : l'instantané doit porter ce que la page affiche, jamais
     * une composition parallèle.
     *
     * @return array<string, mixed>
     */
    private function analysisFor(int $userId, AssetClass $exposure): array
    {
        $analysis = [
            'performances' => $this->valuation->performancesFor($userId, $exposure),
        ];

        if ($exposure->hasSectors()) {
            $analysis['sectorBreakdown'] = $this->sectors->breakdownFor($userId);
        }

        return $analysis;
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
        ];

        /** Une exposition qui ne distribue rien n'a pas de détachements : les porter gonflerait le blob pour rien. */
        if ($this->income->supportsExposure($detail->assetClass)) {
            $page['dividends'] = $this->income->assetHistoryFor($userId, $assetId);
        }

        return $page;
    }
}
