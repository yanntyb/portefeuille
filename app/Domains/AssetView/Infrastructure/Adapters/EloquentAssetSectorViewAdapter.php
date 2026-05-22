<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Models\AssetSector;
use App\Domains\AssetView\DTOs\SectorWeightDTO;
use App\Domains\AssetView\Ports\AssetSectorViewPort;
use Illuminate\Support\Collection;

class EloquentAssetSectorViewAdapter implements AssetSectorViewPort
{
    public function getSectorWeights(int $assetId): Collection
    {
        return AssetSector::query()
            ->where('asset_id', $assetId)
            ->get()
            ->map(SectorWeightDTO::fromModel(...));
    }
}
