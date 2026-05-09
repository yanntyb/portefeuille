<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\AssetSectorFactory;
use App\Domains\Asset\Enums\Sector;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(AssetSectorFactory::class)]
class AssetSector extends Model
{
    /** @use HasFactory<AssetSectorFactory> */
    use HasFactory;

    protected $table = 'security_sectors';

    /** @var list<string> */
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
