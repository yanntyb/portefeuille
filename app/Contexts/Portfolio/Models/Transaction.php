<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Factories\TransactionFactory;
use App\Contexts\Portfolio\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property Carbon $date
 * @property ?int $asset_id
 * @property int $wallet_id
 * @property int $user_id
 * @property TransactionType $type
 * @property string $quantity
 * @property string $unit_price
 * @property string $fees
 * @property ?string $realized_gain
 */
#[ObservedBy(TransactionObserver::class)]
#[UseFactory(TransactionFactory::class)]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $table = 'transactions';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'quantity' => 'decimal:8',
            'unit_price' => 'decimal:4',
            'fees' => 'decimal:2',
            'realized_gain' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
