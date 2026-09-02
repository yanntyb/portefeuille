<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\PortfolioView\Datas\HoldingSnapshotData;
use App\Contexts\PortfolioView\Ports\HoldingsPort;
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
