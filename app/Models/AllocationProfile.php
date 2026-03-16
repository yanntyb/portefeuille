<?php

namespace App\Models;

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
        'wallet_id',
        'user_id',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AllocationProfileItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
