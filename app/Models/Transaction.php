<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
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
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
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
        'date',
        'account_type',
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
            'account_type' => AccountType::class,
            'type' => TransactionType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'fees' => 'decimal:2',
            'realized_gain' => 'decimal:2',
        ];
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
