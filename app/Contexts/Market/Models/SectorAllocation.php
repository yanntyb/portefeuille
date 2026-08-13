<?php

namespace App\Contexts\Market\Models;

use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Factories\SectorAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property int $asset_id
 * @property Sector $sector
 * @property string $weight
 */
#[UseFactory(SectorAllocationFactory::class)]
class SectorAllocation extends Model
{
    /** @use HasFactory<SectorAllocationFactory> */
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

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'asset_id');
    }
}
