<?php

namespace App\Domains\Asset\Models\AssetInfos;

class CryptoAssetInfo extends AssetInfo
{
    protected $table = 'crypto_asset_infos';

    protected $fillable = ['asset_id', 'ticker'];
}
