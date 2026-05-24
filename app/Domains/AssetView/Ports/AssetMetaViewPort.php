<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\ValueObjects\AssetMetaDTO;
use Illuminate\Support\Collection;

interface AssetMetaViewPort
{
    public function getMeta(int $assetId): ?AssetMetaDTO;

    /** @return Collection<int, AssetMetaDTO> */
    public function getAllAssets(): Collection;
}
