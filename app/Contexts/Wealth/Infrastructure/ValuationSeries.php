<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;

class ValuationSeries implements SecuritiesSeriesPort
{
    public function __construct(private BuildEvolutionSeries $evolution) {}

    public function seriesFor(int $userId): ClassSeriesData
    {
        /** Historique complet au pas hebdomadaire, comme le graphe du tableau de bord l'utilisait déjà. */
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->value, count($series->labels)),
            invested: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->invested, count($series->labels)),
        );
    }

    /**
     * @param  list<AssetSeriesData>  $perAsset
     * @param  callable(AssetSeriesData): list<float>  $pick
     * @return list<float>
     */
    private function sum(array $perAsset, callable $pick, int $length): array
    {
        $totals = array_fill(0, $length, 0.0);

        foreach ($perAsset as $asset) {
            foreach ($pick($asset) as $index => $amount) {
                $totals[$index] = ($totals[$index] ?? 0.0) + $amount;
            }
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }
}
