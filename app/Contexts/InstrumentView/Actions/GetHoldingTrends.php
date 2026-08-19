<?php

namespace App\Contexts\InstrumentView\Actions;

use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
use App\Contexts\InstrumentView\Datas\HoldingTrendData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;

class GetHoldingTrends
{
    /** Enough points for a readable sparkline, few enough to keep the payload small. */
    private const MAX_POINTS = 24;

    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
    ) {}

    /**
     * Trends of the instruments the user holds. Only those carry a sparkline, so the catalogue
     * at large is never read: an untouched instrument would cost a price window for nothing.
     *
     * @return list<HoldingTrendData>
     */
    public function __invoke(int $userId, ValuationRange $range = ValuationRange::Max): array
    {
        $since = $this->windowStart($range);
        $assetIds = array_map(
            fn (HoldingSnapshotData $snapshot): int => $snapshot->assetId,
            $this->holdings->holdingsFor($userId),
        );

        $closes = $this->market->closeSeriesSince($assetIds, $since);

        return array_map(
            fn (int $assetId): HoldingTrendData => $this->toTrend($assetId, $closes[$assetId] ?? []),
            $assetIds,
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
    private function toTrend(int $assetId, array $close): HoldingTrendData
    {
        return new HoldingTrendData(
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
