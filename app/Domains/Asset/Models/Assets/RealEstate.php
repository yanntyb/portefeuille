<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\RealEstateFactory;
use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(RealEstateFactory::class)]
class RealEstate extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<RealEstateFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return RealEstateAssetInfo::class;
    }

    public function getIsinAttribute(): ?string
    {
        return $this->details?->isin;
    }

    public function getTickerAttribute(): ?string
    {
        return null;
    }
}
