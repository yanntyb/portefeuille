<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use App\Domains\AssetView\ValueObjects\AssetMetaDTO;
use Illuminate\Support\Collection;

class EloquentAssetMetaViewAdapter implements AssetMetaViewPort
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function getMeta(int $assetId): ?AssetMetaDTO
    {
        $asset = $this->assets->findById($assetId);

        return $asset ? AssetMetaDTO::fromModel($asset) : null;
    }

    public function getAllAssets(): Collection
    {
        return $this->assets->findAll()->map(AssetMetaDTO::fromModel(...));
    }
}
