<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllocationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\AllocationProfileFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'account_type',
        'user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AllocationProfileItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
