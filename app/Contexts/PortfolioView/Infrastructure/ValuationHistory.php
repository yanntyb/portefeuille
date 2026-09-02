<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\AssetLineData;
use App\Contexts\PortfolioView\Datas\AssetValuationData;
use App\Contexts\PortfolioView\Datas\DrawdownData;
use App\Contexts\PortfolioView\Datas\EvolutionData;
use App\Contexts\PortfolioView\Datas\PerformanceLineData;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildExposureSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Services\Drawdown;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

/**
 * Le partage par exposition est passé aux actions telles quelles, jamais refait ici : les
 * performances filtrent avant leur cache, l'évolution après le sien — refiltrer de ce côté
 * rendrait l'un des deux caches incohérent.
 *
 * `drawdownFor()` et `assetSeriesFor()` font exception au remappage pur : elles enchaînent une
 * action et un calculateur du contexte propriétaire (`Valuation\Services\Drawdown`,
 * `Valuation\Services\ValuationCalculator`) sans jamais écrire la formule elles-mêmes — la
 * composition reste ici, le calcul reste chez `Valuation`.
 */
class ValuationHistory implements ValuationPort
{
    /**
     * Amplitude sous laquelle la série d'un actif garde son pas quotidien. Le pas hebdomadaire ne
     * retient qu'un point par semaine ISO : une position ouverte l'avant-veille y tombait à un
     * seul point, qu'ECharts peint sans ligne — un graphe vide en apparence. Un trimestre de pas
     * quotidien reste sous la centaine de points, la semaine reprend au-delà.
     */
    private const DAILY_STEP_MAX_DAYS = 92;

    public function __construct(
        private BuildPortfolioPerformances $performances,
        private BuildEvolutionSeries $evolution,
        private BuildAssetPerformances $assetPerformances,
        private BuildAssetValuationSeries $assetSeries,
        private BuildExposureSeries $exposureSeries,
        private Drawdown $drawdown,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<PerformanceLineData> */
    public function performancesFor(int $userId, AssetClass $exposure): array
    {
        return array_map(
            $this->performanceLine(...),
            ($this->performances)($userId, HoldingScope::ofClasses([$exposure])),
        );
    }

    /**
     * Historique complet au pas hebdomadaire : la fenêtre visible est choisie côté client par le
     * zoom du graphe, ce qui est une décision de cette page et non de la valorisation.
     */
    public function evolutionFor(int $userId, AssetClass $exposure): EvolutionData
    {
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week, [$exposure]);

        return new EvolutionData(
            labels: $series->labels,
            perAsset: array_map(
                fn (AssetSeriesData $line): AssetLineData => new AssetLineData(
                    assetId: $line->assetId,
                    name: $line->name,
                    value: $line->value,
                    invested: $line->invested,
                ),
                $series->perAsset,
            ),
        );
    }

    /** @return list<PerformanceLineData> */
    public function assetPerformancesFor(int $userId, int $assetId): array
    {
        return array_map(
            $this->performanceLine(...),
            ($this->assetPerformances)($userId, $assetId),
        );
    }

    /**
     * Même profondeur que l'évolution d'une exposition. Le pas, lui, se choisit sur l'amplitude
     * réelle de l'historique : la série est demandée au jour, puis ramenée à la semaine seulement
     * si elle est assez longue pour que la semaine lui laisse une courbe.
     */
    public function assetSeriesFor(int $userId, int $assetId): AssetValuationData
    {
        $daily = ($this->assetSeries)(
            $userId,
            $assetId,
            ValuationRange::Max,
            ValuationGranularity::Day,
        );

        $series = $this->calculator->windowAndAggregate($daily, null, $this->stepFor($daily->labels));

        return new AssetValuationData(
            labels: $series->labels,
            valuations: $series->valuations,
            invested: $series->invested,
            prices: $series->prices,
        );
    }

    public function drawdownFor(int $userId, AssetClass $exposure): DrawdownData
    {
        $series = ($this->exposureSeries)($userId, HoldingScope::ofClasses([$exposure]));
        $drawdown = $this->drawdown->of($series->labels, $series->valuations);

        return new DrawdownData(
            maxDepth: $drawdown->maxDepth,
            peakLabel: $drawdown->peakLabel,
            troughLabel: $drawdown->troughLabel,
            currentDepth: $drawdown->currentDepth,
        );
    }

    /**
     * Pas de la série tracée, déduit de son amplitude.
     *
     * @param  list<string>  $labels  Jours croissants, au format `Y-m-d`.
     */
    private function stepFor(array $labels): ValuationGranularity
    {
        if ($labels === []) {
            return ValuationGranularity::Day;
        }

        $span = Carbon::parse($labels[0])->diffInDays(Carbon::parse($labels[count($labels) - 1]));

        return $span > self::DAILY_STEP_MAX_DAYS
            ? ValuationGranularity::Week
            : ValuationGranularity::Day;
    }

    private function performanceLine(PerformanceData $performance): PerformanceLineData
    {
        return new PerformanceLineData(
            key: $performance->key,
            label: $performance->label,
            startDate: $performance->startDate,
            valueStart: $performance->valueStart,
            contributions: $performance->contributions,
            gain: $performance->gain,
            pct: $performance->pct,
        );
    }
}
