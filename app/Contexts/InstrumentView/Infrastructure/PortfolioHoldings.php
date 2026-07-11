<?php

namespace App\Contexts\InstrumentView\Infrastructure;

use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\Portfolio\Models\Holding;

class PortfolioHoldings implements HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array
    {
        return Holding::query()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Holding $holding) => $this->toSnapshot($holding))
            ->values()
            ->all();
    }

    public function holdingFor(int $userId, int $assetId): ?HoldingSnapshotData
    {
        $holding = Holding::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->first();

        return $holding !== null ? $this->toSnapshot($holding) : null;
    }

    private function toSnapshot(Holding $holding): HoldingSnapshotData
    {
        return new HoldingSnapshotData(
            assetId: (int) $holding->asset_id,
            quantity: (float) $holding->quantity,
            avgCost: $holding->avg_cost !== null ? (float) $holding->avg_cost : null,
        );
    }
}
