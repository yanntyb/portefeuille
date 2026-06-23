<?php

namespace App\Contexts\Market\Models;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Factories\InstrumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property string $name
 * @property ?string $isin
 * @property ?string $ticker
 * @property InstrumentType $type
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(InstrumentFactory::class)]
class Instrument extends Model
{
    /** @use HasFactory<InstrumentFactory> */
    use HasFactory;

    protected $table = 'assets';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('market', function (Builder $query): void {
            $query->whereIn('type', InstrumentType::values());
        });

        static::creating(function (Instrument $instrument): void {
            if ($instrument->type === null) {
                $instrument->type = InstrumentType::Stock;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InstrumentType::class,
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'asset_id');
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(SectorAllocation::class, 'asset_id');
    }
}
