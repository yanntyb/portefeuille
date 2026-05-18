<?php

namespace App\Domains\Asset\Models\AssetInfos;

class RealEstateAssetInfo extends AssetInfo
{
    protected $table = 'realestate_asset_infos';

    protected $fillable = ['asset_id', 'isin'];
}
