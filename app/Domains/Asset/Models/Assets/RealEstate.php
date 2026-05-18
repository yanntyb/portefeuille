<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\RealEstateFactory;
use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(RealEstateFactory::class)]
class RealEstate extends Asset
{
    /** @use HasFactory<RealEstateFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return RealEstateAssetInfo::class;
    }

    public function getTickerAttribute(): ?string
    {
        return null;
    }
}
