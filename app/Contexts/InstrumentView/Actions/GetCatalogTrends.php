<?php

namespace App\Contexts\InstrumentView\Actions;

use App\Contexts\InstrumentView\Datas\CatalogTrendData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;

class GetCatalogTrends
{
    /** Enough points for a readable sparkline, few enough to keep the payload small. */
    private const MAX_POINTS = 24;

    public function __construct(private MarketDataPort $market) {}

    /** @return list<CatalogTrendData> */
    public function __invoke(ValuationRange $range = ValuationRange::Max): array
    {
        $since = $this->windowStart($range);
        $instruments = $this->market->listInstruments();

        $closes = $this->market->closeSeriesSince(
            array_map(fn (InstrumentSummaryData $summary): int => $summary->id, $instruments),
            $since,
        );

        return array_map(
            fn (InstrumentSummaryData $summary): CatalogTrendData => $this->toTrend(
                $summary->id,
                $closes[$summary->id] ?? [],
            ),
            $instruments,
        );
    }

    private function windowStart(ValuationRange $range): Carbon
    {
        $months = $range->months();

        return $months === null
            ? Carbon::createFromTimestamp(0)
            : Carbon::now()->subMonths($months);
    }

    /** @param list<float> $close */
    private function toTrend(int $assetId, array $close): CatalogTrendData
    {
        return new CatalogTrendData(
            assetId: $assetId,
            changePct: $this->changePct($close),
            points: $this->downsample($close),
        );
    }

    /** @param list<float> $close */
    private function changePct(array $close): ?float
    {
        $count = count($close);

        if ($count < 2 || $close[0] === 0.0) {
            return null;
        }

        return ($close[$count - 1] - $close[0]) / $close[0] * 100;
    }

    /**
     * @param  list<float>  $close
     * @return list<float>
     */
    private function downsample(array $close): array
    {
        $count = count($close);

        if ($count <= self::MAX_POINTS) {
            return $close;
        }

        $points = [];
        for ($step = 0; $step < self::MAX_POINTS; $step++) {
            $points[] = $close[(int) round($step * ($count - 1) / (self::MAX_POINTS - 1))];
        }

        return $points;
    }
}
