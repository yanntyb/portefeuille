<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\DTOs\SectorWeightDTO;
use Illuminate\Support\Collection;

interface AssetSectorViewPort
{
    /** @return Collection<int, SectorWeightDTO> */
    public function getSectorWeights(int $assetId): Collection;
}
