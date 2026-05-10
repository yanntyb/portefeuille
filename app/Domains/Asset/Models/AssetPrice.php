<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Factories\AssetPriceFactory;
use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $asset_id
 * @property-read \Illuminate\Support\Carbon $date
 * @property-read string $open
 * @property-read string $high
 * @property-read string $low
 * @property-read string $close
 * @property-read int $volume
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
#[UseFactory(AssetPriceFactory::class)]
class AssetPrice extends Model
{
    /** @use HasFactory<AssetPriceFactory> */
    use HasFactory;

    protected $table = 'asset_prices';

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'date',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'open' => 'decimal:4',
            'high' => 'decimal:4',
            'low' => 'decimal:4',
            'close' => 'decimal:4',
            'volume' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
