<?php

namespace App\Domains\Asset\Models\AssetInfos;

class BondAssetInfo extends AssetInfo
{
    protected $table = 'bond_asset_infos';

    protected $fillable = ['asset_id', 'isin', 'ticker'];
}
