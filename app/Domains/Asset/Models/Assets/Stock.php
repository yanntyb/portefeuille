<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\StockFactory;
use App\Domains\Asset\Models\AssetInfos\StockAssetInfo;
use App\Domains\Asset\Models\AssetSector;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(StockFactory::class)]
class Stock extends Asset
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return StockAssetInfo::class;
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
