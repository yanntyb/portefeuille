<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read string $monthly_rent
 * @property-read Carbon $start_date
 * @property-read ?Carbon $end_date
 */
#[UseFactory(LeaseFactory::class)]
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'monthly_rent', 'start_date', 'end_date'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(RentException::class)->orderBy('month');
    }
}
