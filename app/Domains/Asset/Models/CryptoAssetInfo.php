<?php

namespace App\Domains\Asset\Models;

class CryptoAssetInfo extends AssetInfo
{
    protected $table = 'crypto_asset_infos';

    protected $fillable = ['asset_id', 'ticker'];

    public function getIsinAttribute(): ?string
    {
        return null;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->attributes['ticker'] ?? null;
    }
}
