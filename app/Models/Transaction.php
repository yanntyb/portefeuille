<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $user_id
 */
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'date',
        'account_type',
        'security_id',
        'broker',
        'quantity',
        'unit_price',
        'fees',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'account_type' => AccountType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'fees' => 'decimal:2',
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
