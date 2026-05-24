<?php

namespace App\Domains\AssetView\ValueObjects;

use App\Domains\Asset\Models\Assets\Asset;

readonly class AssetMetaDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public ?string $ticker = null,
        public ?string $isin = null,
    ) {}

    public static function fromModel(Asset $asset): self
    {
        // Check if infos relationship is already loaded, otherwise fetch it
        $infos = $asset->relationLoaded('infos') ? $asset->infos : $asset->infos()->first();

        return new self(
            id: $asset->id,
            name: $asset->name,
            type: $asset->type->value,
            ticker: $infos?->ticker ?? null,
            isin: $infos?->isin ?? null,
        );
    }
}
