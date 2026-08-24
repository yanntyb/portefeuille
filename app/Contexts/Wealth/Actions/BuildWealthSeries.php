<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassValuesData;
use App\Contexts\Wealth\Datas\WealthSeriesData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Ports\AssetClassPort;
use App\Contexts\Wealth\Services\SeriesAligner;

/** Le patrimoine dans le temps, les classes empilables sur une grille commune. */
class BuildWealthSeries
{
    public function __construct(
        private AssetClassRegistry $classes,
        private SeriesAligner $aligner,
    ) {}

    public function __invoke(int $userId): WealthSeriesData
    {
        $classes = $this->classes->all();

        /** @var list<ClassSeriesData> $series */
        $series = array_map(fn (AssetClassPort $class): ClassSeriesData => $class->seriesFor($userId), $classes);

        $labels = $this->aligner->union(...array_map(
            fn (ClassSeriesData $one): array => $one->labels,
            $series,
        ));

        if ($labels === []) {
            return WealthSeriesData::empty();
        }

        $values = [];
        $invested = array_fill(0, count($labels), 0.0);

        foreach ($classes as $index => $class) {
            $values[] = new ClassValuesData(
                key: $class->key(),
                label: $class->label(),
                color: $class->color(),
                values: $this->aligner->onto($labels, $series[$index]->labels, $series[$index]->value),
            );

            foreach ($this->aligner->onto($labels, $series[$index]->labels, $series[$index]->invested) as $at => $amount) {
                $invested[$at] += $amount;
            }
        }

        return new WealthSeriesData(
            labels: $labels,
            classes: $values,
            invested: array_map(fn (float $amount): float => round($amount, 2), $invested),
        );
    }
}
