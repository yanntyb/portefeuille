<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthSeriesData;
use App\Contexts\Wealth\Ports\RealEstatePort;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use App\Contexts\Wealth\Services\SeriesAligner;

/** Le patrimoine dans le temps, les deux classes empilables sur une grille commune. */
class BuildWealthSeries
{
    public function __construct(
        private SecuritiesSeriesPort $securities,
        private RealEstatePort $realEstate,
        private SeriesAligner $aligner,
    ) {}

    public function __invoke(int $userId): WealthSeriesData
    {
        $securities = $this->securities->seriesFor($userId);
        $realEstate = $this->realEstate->seriesFor($userId);

        $labels = $this->aligner->union($securities->labels, $realEstate->labels);

        if ($labels === []) {
            return WealthSeriesData::empty();
        }

        $securitiesValue = $this->aligner->onto($labels, $securities->labels, $securities->value);
        $realEstateValue = $this->aligner->onto($labels, $realEstate->labels, $realEstate->value);
        $securitiesInvested = $this->aligner->onto($labels, $securities->labels, $securities->invested);
        $realEstateInvested = $this->aligner->onto($labels, $realEstate->labels, $realEstate->invested);

        return new WealthSeriesData(
            labels: $labels,
            securities: $securitiesValue,
            realEstate: $realEstateValue,
            invested: array_map(
                fn (float $left, float $right): float => round($left + $right, 2),
                $securitiesInvested,
                $realEstateInvested,
            ),
        );
    }
}
