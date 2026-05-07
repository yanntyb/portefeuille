<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $allocation_profile_id
 * @property-read int $security_id
 * @property-read string $target_percentage
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class AllocationProfileItem extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Portfolio\Models\AllocationProfileItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'allocation_profile_id',
        'security_id',
        'target_percentage',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_percentage' => 'decimal:2',
        ];
    }

    public function allocationProfile(): BelongsTo
    {
        return $this->belongsTo(AllocationProfile::class);
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }
}
