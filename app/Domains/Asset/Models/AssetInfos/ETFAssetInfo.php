<?php

namespace App\Domains\Asset\Models\AssetInfos;

class ETFAssetInfo extends AssetInfo
{
    protected $table = 'etf_asset_infos';

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
