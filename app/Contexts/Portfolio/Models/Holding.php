<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Factories\HoldingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $asset_id
 * @property int $wallet_id
 * @property int $user_id
 * @property string $quantity
 * @property ?string $avg_cost
 */
#[UseFactory(HoldingFactory::class)]
class Holding extends Model
{
    /** @use HasFactory<HoldingFactory> */
    use HasFactory;

    protected $table = 'holdings_projection';

    public $incrementing = false;

    protected $primaryKey = 'asset_id';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'avg_cost' => 'decimal:4',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'asset_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Holding>  $query
     * @return Builder<Holding>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('asset_id', $this->getAttribute('asset_id'))
            ->where('wallet_id', $this->getAttribute('wallet_id'));
    }

    /**
     * @param  Builder<Holding>  $query
     * @return Builder<Holding>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $query
            ->where('asset_id', $this->getOriginal('asset_id', $this->getAttribute('asset_id')))
            ->where('wallet_id', $this->getOriginal('wallet_id', $this->getAttribute('wallet_id')));
    }
}
