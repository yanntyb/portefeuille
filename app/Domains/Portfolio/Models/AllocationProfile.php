<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $name
 * @property int $wallet_id
 * @property int $user_id
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class AllocationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Portfolio\Models\AllocationProfileFactory> */
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
