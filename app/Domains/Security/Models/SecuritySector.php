<?php

namespace App\Domains\Security\Models;

use App\Domains\Security\Enums\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $security_id
 * @property-read Sector $sector
 * @property-read string $weight
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class SecuritySector extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Security\Models\SecuritySectorFactory> */
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
