<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read string $principal
 * @property-read string $annual_rate fraction : `0.024` = 2,4 %/an
 * @property-read int $term_months
 * @property-read Carbon $start_date
 * @property-read string $monthly_insurance
 */
#[UseFactory(LoanFactory::class)]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'principal', 'annual_rate', 'term_months', 'start_date', 'monthly_insurance'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'annual_rate' => 'decimal:5',
            'term_months' => 'integer',
            'start_date' => 'date',
            'monthly_insurance' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
