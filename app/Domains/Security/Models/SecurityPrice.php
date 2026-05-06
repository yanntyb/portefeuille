<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPrice extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Security\Models\SecurityPriceFactory> */
    use HasFactory;

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
