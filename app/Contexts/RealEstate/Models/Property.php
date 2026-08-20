<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $name
 * @property-read ?string $address
 * @property-read Carbon $acquisition_date
 * @property-read string $acquisition_price
 * @property-read string $acquisition_fees
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(PropertyFactory::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'name', 'address', 'acquisition_date', 'acquisition_price', 'acquisition_fees'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_price' => 'decimal:2',
            'acquisition_fees' => 'decimal:2',
        ];
    }

    /** @return HasMany<PropertyValuation, $this> */
    public function valuations(): HasMany
    {
        return $this->hasMany(PropertyValuation::class)->orderBy('date');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->orderBy('start_date');
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->orderBy('start_date');
    }

    /** @return HasMany<PropertyExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(PropertyExpense::class)->orderBy('date');
    }
}
