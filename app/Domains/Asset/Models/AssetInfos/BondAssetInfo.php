<?php

namespace App\Domains\Asset\Models\AssetInfos;

class BondAssetInfo extends AssetInfo
{
    protected $table = 'bond_asset_infos';

    protected $fillable = ['asset_id', 'isin', 'ticker'];

    public function getIsinAttribute(): ?string
    {
        return $this->attributes['isin'] ?? null;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->attributes['ticker'] ?? null;
    }
}
