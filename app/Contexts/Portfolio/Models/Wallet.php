<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property int $user_id
 * @property string $name
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
}
