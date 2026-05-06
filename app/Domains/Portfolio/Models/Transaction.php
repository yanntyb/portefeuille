<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Observers\TransactionObserver;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $user_id
 */
#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Portfolio\Models\TransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check()) {
                $query->where('transactions.user_id', auth()->id());
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'date',
        'type',
        'security_id',
        'broker',
        'quantity',
        'unit_price',
        'fees',
        'realized_gain',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'fees' => 'decimal:2',
            'realized_gain' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
