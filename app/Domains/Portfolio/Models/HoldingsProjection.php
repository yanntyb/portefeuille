<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldingsProjection extends Model
{
    protected $table = 'holdings_projection';

    protected $fillable = [
        'asset_id',
        'wallet_id',
        'user_id',
        'quantity',
        'avg_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'avg_cost' => 'decimal:4',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
