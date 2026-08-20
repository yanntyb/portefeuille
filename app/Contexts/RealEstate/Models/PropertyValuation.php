<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\PropertyValuationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read Carbon $date
 * @property-read string $value
 */
#[UseFactory(PropertyValuationFactory::class)]
class PropertyValuation extends Model
{
    /** @use HasFactory<PropertyValuationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'date', 'value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
