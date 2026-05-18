<?php

namespace App\Domains\Asset\Models\AssetInfos;

use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class AssetInfo extends Model
{
    protected $guarded = ['id'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
