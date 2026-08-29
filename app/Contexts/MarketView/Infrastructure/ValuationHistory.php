<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\AssetLineData;
use App\Contexts\MarketView\Datas\EvolutionData;
use App\Contexts\MarketView\Datas\PerformanceLineData;
use App\Contexts\MarketView\Ports\ValuationPort;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Enums\ValuationGranularity;

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
