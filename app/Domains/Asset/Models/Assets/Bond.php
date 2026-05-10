<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\BondFactory;
use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(BondFactory::class)]
class Bond extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<BondFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return BondAssetInfo::class;
    }

    public function getIsinAttribute(): ?string
    {
        return $this->details?->isin;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->details?->ticker;
    }
}
