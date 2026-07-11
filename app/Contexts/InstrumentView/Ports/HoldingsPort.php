<?php

namespace App\Contexts\InstrumentView\Ports;

interface HoldingsPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\HoldingSnapshotData> */
    public function holdingsFor(int $userId): array;

    public function holdingFor(int $userId, int $assetId): ?\App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
}
