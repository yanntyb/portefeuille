<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
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
#[UseFactory(StockFactory::class)]
class Stock extends Asset
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
