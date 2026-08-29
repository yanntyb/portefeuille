<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;

/** Pur remappage : la position par actif est calculée par `Portfolio`, qui en est propriétaire. */
class PortfolioHoldings implements HoldingsPort
{
    public function __construct(private GetPortfolioPositions $positions) {}

    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array
    {
        return array_values(array_map(
            fn (PositionLineData $position): HoldingSnapshotData => new HoldingSnapshotData(
                assetId: $position->assetId,
                quantity: $position->quantity,
                avgCost: $position->avgCost,
            ),
            ($this->positions)($userId),
        ));
    }
}
