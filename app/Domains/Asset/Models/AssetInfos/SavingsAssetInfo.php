<?php

namespace App\Domains\Asset\Models\AssetInfos;

class SavingsAssetInfo extends AssetInfo
{
    protected $table = 'savings_asset_infos';

    protected $fillable = ['asset_id'];

    public function getIsinAttribute(): ?string
    {
        return null;
    }

    public function getTickerAttribute(): ?string
    {
        return null;
    }
}
