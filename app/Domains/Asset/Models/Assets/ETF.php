<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\ETFFactory;
use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use App\Domains\Asset\Models\AssetSector;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ETFFactory::class)]
class ETF extends Asset
{
    /** @use HasFactory<ETFFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return ETFAssetInfo::class;
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
