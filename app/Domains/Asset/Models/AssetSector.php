<?php

namespace App\Domains\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSector extends Model
{
    protected $table = 'security_sectors';

    protected $fillable = ['asset_id', 'sector', 'weight'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
