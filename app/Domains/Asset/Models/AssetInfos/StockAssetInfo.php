<?php

namespace App\Domains\Asset\Models\AssetInfos;

class StockAssetInfo extends AssetInfo
{
    protected $table = 'stock_asset_infos';

    protected $fillable = ['asset_id', 'isin', 'ticker'];
}
