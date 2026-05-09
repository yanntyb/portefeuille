<?php

namespace App\Domains\Asset\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $isin
 * @property string $name
 * @property string $ticker
 * @property-read ?int $total_quantity
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class Stock extends Asset
{
    /** @use HasFactory<\Database\Factories\Domains\Asset\Models\StockFactory> */
    use HasFactory;

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
