<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldingsProjection extends Model
{
    protected $table = 'holdings_projection';

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check()) {
                $query->where('holdings_projection.user_id', auth()->id());
            }
        });
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->withoutGlobalScope('user')->where('holdings_projection.user_id', $userId);
    }

    public $incrementing = false;

    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'asset_id',
        'wallet_id',
        'quantity',
        'avg_cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'avg_cost' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
