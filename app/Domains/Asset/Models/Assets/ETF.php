<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\ETFFactory;
use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use App\Domains\Asset\Models\AssetSector;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $isin
 * @property string $name
 * @property string $ticker
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
#[UseFactory(ETFFactory::class)]
class ETF extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<ETFFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return ETFAssetInfo::class;
    }

    public function getIsinAttribute(): ?string
    {
        return $this->details?->isin;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->details?->ticker;
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
