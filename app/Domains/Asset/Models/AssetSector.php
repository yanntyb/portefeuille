<?php

namespace App\Domains\Asset\Models;

use App\Domains\Security\Enums\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSector extends Model
{
    protected $table = 'security_sectors';

    protected $fillable = ['asset_id', 'sector', 'weight'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sector' => Sector::class,
            'weight' => 'decimal:6',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
