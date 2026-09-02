<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Services\BasketIndex;
use App\Contexts\Market\Services\Correlation;
use App\Contexts\Market\Services\FiftyTwoWeekRange;
use App\Contexts\PortfolioView\Datas\AnalysisInstrumentData;
use App\Contexts\PortfolioView\Datas\ClassAnalysisData;
use App\Contexts\PortfolioView\Datas\HoldingRowData;
use App\Contexts\PortfolioView\Ports\ClassAnalysisPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Services\CorrelationWindow;
use App\Contexts\PortfolioView\Services\PriceHistoryWindow;
use App\Contexts\Valuation\Actions\BuildExposureSeries;
use App\Contexts\Valuation\Services\Drawdown;

/**
 * Compose l'analyse d'une exposition : le portefeuille dit ce qu'elle porte et à quel poids, le
 * dépôt de cours fournit la matière, les calculateurs des contextes propriétaires font les
 * formules. Cet adaptateur choisit les instruments et les fenêtres, il n'écrit aucun calcul.
 *
 * Deux fenêtres cohabitent, et ce choix lui revient : l'indice de la poche court sur toute la
 * profondeur servie à la fiche instrument — une chute mémorable a besoin d'années — quand la
 * matrice ne regarde qu'un an, car une corrélation vieillit vite.
 *
 * Deux séries aussi, et pour deux questions distinctes. La chute maximale se lit sur l'indice, à
 * pondération courante : elle raconte ce que les marchés ont fait subir au panier, indépendamment
 * des versements. La distance au plus-haut se lit sur la valorisation de la poche, celle-là même
 * que trace le graphe au-dessus : elle répond à « où en suis-je par rapport à mon sommet », et
 * cette question-là compte les allégements et les apports. Deux repères posés côte à côte sur la
 * même série se contrediraient moins, mais l'un des deux mentirait.
 */
class ClassAnalysis implements ClassAnalysisPort
{
    /** Au-delà, la matrice ne se lit plus : huit colonnes tiennent encore sur un téléphone. */
    public const MAX_INSTRUMENTS = 8;

    public function __construct(
        private PortfolioOverviewPort $overview,
        private PriceRepositoryContract $prices,
        private Correlation $correlation,
        private BasketIndex $basket,
        private Drawdown $drawdown,
        private FiftyTwoWeekRange $fiftyTwoWeeks,
        private BuildExposureSeries $exposureSeries,
    ) {}

    public function forClass(int $userId, AssetClass $exposure): ClassAnalysisData
    {
        $holdings = $this->overview->overviewFor($userId, $exposure)->holdings;
        $weights = $this->heaviestWeights($holdings);

        if ($weights === []) {
            return ClassAnalysisData::empty();
        }

        $closesByAsset = $this->closesByAsset(array_keys($weights));
        $index = $this->basket->of($closesByAsset, $weights);
        $valuations = ($this->exposureSeries)($userId, [$exposure])->valuations;

        return new ClassAnalysisData(
            maxDrawdown: $this->drawdown->of($index->labels, $index->values)->maxDepth,
            high52wGapPct: $this->fiftyTwoWeeks->of($valuations)?->gapPct,
            instruments: $this->instrumentsOf($holdings, array_keys($weights)),
            correlations: $this->correlation->matrix($this->recentOf($closesByAsset))->rows,
        );
    }

    /**
     * Les lignes de l'exposition, du plus gros poids au plus petit, plafonnées. Les enveloppes se
     * confondent : une même valeur détenue sur deux comptes est une seule ligne de la matrice.
     *
     * @param  list<HoldingRowData>  $holdings
     * @return array<int, float> Valeur de marché par actif, dans l'ordre d'affichage.
     */
    private function heaviestWeights(array $holdings): array
    {
        $values = [];

        foreach ($holdings as $line) {
            if ($line->marketValue === null || $line->marketValue <= 0.0) {
                continue;
            }

            $values[$line->assetId] = ($values[$line->assetId] ?? 0.0) + $line->marketValue;
        }

        arsort($values);

        return array_slice($values, 0, self::MAX_INSTRUMENTS, preserve_keys: true);
    }

    /**
     * Les clôtures de chaque actif, par date, dans l'ordre des poids. Les lignes brutes du dépôt
     * évitent d'hydrater cinq ans de cours par instrument pour n'en lire que trois colonnes.
     *
     * @param  list<int>  $assetIds
     * @return array<int, array<string, float>>
     */
    private function closesByAsset(array $assetIds): array
    {
        $closes = array_fill_keys($assetIds, []);

        foreach ($this->prices->dailyClosesForAssetsSince($assetIds, PriceHistoryWindow::since()) as $row) {
            $closes[$row['assetId']][$row['date']] = $row['close'];
        }

        return $closes;
    }

    /**
     * Les mêmes séries, resserrées sur la fenêtre de corrélation.
     *
     * @param  array<int, array<string, float>>  $closesByAsset
     * @return array<int, array<string, float>>
     */
    private function recentOf(array $closesByAsset): array
    {
        $since = CorrelationWindow::since()->format('Y-m-d');

        return array_map(
            fn (array $closes): array => array_filter(
                $closes,
                fn (string $date): bool => $date >= $since,
                ARRAY_FILTER_USE_KEY,
            ),
            $closesByAsset,
        );
    }

    /**
     * @param  list<HoldingRowData>  $holdings
     * @param  list<int>  $assetIds
     * @return list<AnalysisInstrumentData>
     */
    private function instrumentsOf(array $holdings, array $assetIds): array
    {
        $labels = [];

        foreach ($holdings as $line) {
            $labels[$line->assetId] ??= $line->ticker ?? $line->assetName;
        }

        return array_map(
            fn (int $assetId): AnalysisInstrumentData => new AnalysisInstrumentData(
                assetId: $assetId,
                label: $labels[$assetId],
            ),
            $assetIds,
        );
    }
}
