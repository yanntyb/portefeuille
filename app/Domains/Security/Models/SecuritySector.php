<?php

namespace App\Domains\Security\Models;

use App\Domains\Security\Enums\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySector extends Model
{
    /** @use HasFactory<\Database\Factories\SecuritySectorFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'security_id',
        'sector',
        'weight',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sector' => Sector::class,
            'weight' => 'decimal:6',
        ];
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }
}
