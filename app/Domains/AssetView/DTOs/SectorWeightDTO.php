<?php

namespace App\Domains\AssetView\DTOs;

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\AssetSector;

readonly class SectorWeightDTO
{
    public function __construct(
        public Sector $sector,
        public float $weight,
    ) {}

    public static function fromModel(AssetSector $model): self
    {
        return new self(
            sector: $model->sector,
            weight: (float) $model->weight,
        );
    }
}
