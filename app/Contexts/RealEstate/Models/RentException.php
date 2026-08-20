<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\RentExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $lease_id
 * @property-read Carbon $month
 * @property-read string $amount_override
 * @property-read ?string $note
 */
#[UseFactory(RentExceptionFactory::class)]
class RentException extends Model
{
    /** @use HasFactory<RentExceptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['lease_id', 'month', 'amount_override', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount_override' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
