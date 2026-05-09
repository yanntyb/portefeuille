<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\ETFFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $isin
 * @property string $name
 * @property string $ticker
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
#[UseFactory(ETFFactory::class)]
class ETF extends Asset
{
    /** @use HasFactory<ETFFactory> */
    use HasFactory;

    public function sectors(): HasMany
    {
        return $this->hasMany(AssetSector::class, 'asset_id');
    }
}
