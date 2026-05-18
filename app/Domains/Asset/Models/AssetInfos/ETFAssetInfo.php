<?php

namespace App\Domains\Asset\Models\AssetInfos;

class ETFAssetInfo extends AssetInfo
{
    protected $table = 'etf_asset_infos';

    protected $fillable = ['asset_id', 'isin', 'ticker'];
}
