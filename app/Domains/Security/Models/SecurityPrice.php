<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $security_id
 * @property-read \Illuminate\Support\Carbon $date
 * @property-read string $open
 * @property-read string $high
 * @property-read string $low
 * @property-read string $close
 * @property-read int $volume
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class SecurityPrice extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Security\Models\SecurityPriceFactory> */
    use HasFactory;

    protected $table = 'asset_prices';

    /** @var list<string> */
    protected $fillable = [
        'security_id',
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

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }
}
