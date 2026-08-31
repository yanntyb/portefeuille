<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property int $user_id
 * @property string $name
 * @property AccountType $account_type
 * @property ?Carbon $opened_at
 */
#[UseFactory(WalletFactory::class)]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $table = 'wallets';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'opened_at' => 'date',
        ];
    }
}
