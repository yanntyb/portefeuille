<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\TrueRangeBar;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Services\AverageTrueRange;
use App\Contexts\Market\Services\FiftyTwoWeekRange;
use App\Contexts\Market\Services\MovingAverage;
use App\Contexts\Market\Services\PriceGap;
use App\Contexts\Market\Services\RelativeStrengthIndex;
use App\Contexts\MarketView\Datas\InstrumentAnalysisData;
use App\Contexts\MarketView\Ports\InstrumentAnalysisPort;
use App\Contexts\MarketView\Services\PriceHistoryWindow;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\Portfolio\Services\PositionWeight;
use App\Contexts\Valuation\Services\Drawdown;

/**
 * Compose les repères d'analyse d'une position : le dépôt de cours fournit la matière, les
 * calculateurs des contextes propriétaires font les formules, cet adaptateur ne fait que les
 * enchaîner. Aucun pourcentage ne s'écrit ici — `PriceGap` et les Datas s'en chargent.
 *
 * `GetPortfolioPositions` est injectée et jamais construite : elle est liée en `scoped` et
 * mémoïse ses positions par utilisateur, si bien que la fiche et l'instantané la relisent sans
 * repayer la lecture du portefeuille.
 *
 * Le poids se rapporte au portefeuille entier, toutes expositions confondues — c'est l'échelle à
 * laquelle se décide la taille d'un renfort — d'où l'action plutôt que `PortfolioOverviewPort`,
 * qui ne répond que par exposition.
 */
class InstrumentAnalysis implements InstrumentAnalysisPort
{
    /** La moyenne longue de référence : deux cents séances, environ dix mois cotés. */
    private const LONG_TERM_SESSIONS = 200;

    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private MovingAverage $movingAverage,
        private PriceGap $priceGap,
        private RelativeStrengthIndex $rsi,
        private AverageTrueRange $atr,
        private FiftyTwoWeekRange $fiftyTwoWeeks,
        private Drawdown $drawdown,
        private PositionWeight $weight,
    ) {}

    public function forAsset(int $userId, int $assetId): ?InstrumentAnalysisData
    {
        $positions = ($this->positions)($userId);
        $position = $positions[$assetId] ?? null;

        if ($position === null) {
            return null;
        }

        $prices = $this->prices->forAssetSince($assetId, PriceHistoryWindow::since());

        if ($prices->isEmpty()) {
            return InstrumentAnalysisData::empty();
        }

        $labels = $prices->map(fn (Price $price): string => $price->date->format('Y-m-d'))->values()->all();
        $closes = $prices->map(fn (Price $price): float => (float) $price->close)->values()->all();
        $bars = $prices->map(fn (Price $price): TrueRangeBar => new TrueRangeBar(
            high: (float) ($price->high ?? $price->close),
            low: (float) ($price->low ?? $price->close),
            close: (float) $price->close,
        ))->values()->all();

        $lastClose = $closes[count($closes) - 1];
        $movingAverage = $this->movingAverage->of($closes, self::LONG_TERM_SESSIONS);
        $atr = $this->atr->of($bars);
        $fiftyTwoWeeks = $this->fiftyTwoWeeks->of($closes);

        return new InstrumentAnalysisData(
            pru: $position->avgCost,
            pruGapPct: $this->priceGap->pct($position->avgCost, $lastClose),
            ma200: $movingAverage,
            ma200GapPct: $this->priceGap->pct($movingAverage, $lastClose),
            rsi14: $this->rsi->of($closes),
            high52w: $fiftyTwoWeeks?->high,
            high52wGapPct: $fiftyTwoWeeks?->gapPct,
            atr: $atr?->value,
            atrPct: $atr?->percent,
            maxDrawdown: $this->drawdown->of($labels, $closes)->maxDepth,
            portfolioWeightPct: $this->weight->of(
                $position->marketValue,
                array_map(fn (PositionLineData $line): ?float => $line->marketValue, array_values($positions)),
            ),
        );
    }
}
