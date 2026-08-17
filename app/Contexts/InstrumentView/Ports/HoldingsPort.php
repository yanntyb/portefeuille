<?php

namespace App\Contexts\InstrumentView\Ports;

use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;

interface HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array;

    public function holdingFor(int $userId, int $assetId): ?HoldingSnapshotData;
}
