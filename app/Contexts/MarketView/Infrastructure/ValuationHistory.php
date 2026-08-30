<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\AssetLineData;
use App\Contexts\MarketView\Datas\AssetValuationData;
use App\Contexts\MarketView\Datas\DrawdownData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Ports\ValuationPort;
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
use InvalidArgumentException;

/**
 * Pur remappage : le partage par exposition est passé aux actions telles quelles, jamais refait
 * ici. Les performances filtrent avant leur cache, l'évolution après le sien — refiltrer de ce
 * côté rendrait l'un des deux caches incohérent.
 */
class ValuationHistory implements ValuationPort
{
    public function __construct(
        private BuildPortfolioPerformances $performances,
        private BuildEvolutionSeries $evolution,
        private BuildAssetPerformances $assetPerformances,
        private BuildAssetValuationSeries $assetSeries,
        private BuildExposureSeries $exposureSeries,
        private Drawdown $drawdown,
    ) {}

    /** @return list<PerformanceLineData> */
    public function performancesFor(int $userId, AssetClass $exposure): array
    {
        return array_map(
            $this->performanceLine(...),
            ($this->performances)($userId, [$exposure]),
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

    /** Même profondeur et même pas que l'évolution d'une exposition, pour la même raison. */
    public function assetSeriesFor(int $userId, int $assetId): AssetValuationData
    {
        $series = ($this->assetSeries)(
            $userId,
            $assetId,
            ValuationRange::Max,
            ValuationGranularity::Week,
        );

        return new AssetValuationData(
            labels: $series->labels,
            valuations: $series->valuations,
            invested: $series->invested,
            prices: $series->prices,
        );
    }

    /**
     * `BuildExposureSeries` garantit `labels` et `valuations` de même longueur ; on le vérifie
     * plutôt que de le supposer, `Drawdown::of` levant sinon une exception.
     */
    public function drawdownFor(int $userId, AssetClass $exposure): DrawdownData
    {
        $series = ($this->exposureSeries)($userId, [$exposure]);

        if (count($series->labels) !== count($series->valuations)) {
            throw new InvalidArgumentException(
                'labels et valuations doivent avoir la même longueur (labels: '.count($series->labels).', valuations: '.count($series->valuations).')'
            );
        }

        $drawdown = $this->drawdown->of($series->labels, $series->valuations);

        return new DrawdownData(
            maxDepth: $drawdown->maxDepth,
            peakLabel: $drawdown->peakLabel,
            troughLabel: $drawdown->troughLabel,
            currentDepth: $drawdown->currentDepth,
        );
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
