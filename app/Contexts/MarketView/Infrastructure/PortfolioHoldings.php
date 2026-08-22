<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\Portfolio\Models\Holding;
use Illuminate\Support\Collection;

class PortfolioHoldings implements HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array
    {
        return Holding::query()
            ->where('user_id', $userId)
            ->get()
            ->groupBy('asset_id')
            ->map(fn (Collection $rows) => $this->aggregate((int) $rows->first()->asset_id, $rows))
            ->values()
            ->all();
    }

    public function holdingFor(int $userId, int $assetId): ?HoldingSnapshotData
    {
        $rows = Holding::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->get();

        return $rows->isNotEmpty() ? $this->aggregate($assetId, $rows) : null;
    }

    /** @param Collection<int, Holding> $rows */
    private function aggregate(int $assetId, Collection $rows): HoldingSnapshotData
    {
        $quantity = (float) $rows->sum(fn (Holding $holding) => (float) $holding->quantity);

        $costRows = $rows->filter(fn (Holding $holding) => $holding->avg_cost !== null);
        $qtyWithCost = (float) $costRows->sum(fn (Holding $holding) => (float) $holding->quantity);
        $avgCost = $qtyWithCost > 0.0
            ? (float) $costRows->sum(fn (Holding $holding) => (float) $holding->quantity * (float) $holding->avg_cost) / $qtyWithCost
            : null;

        return new HoldingSnapshotData(
            assetId: $assetId,
            quantity: $quantity,
            avgCost: $avgCost,
        );
    }
}
