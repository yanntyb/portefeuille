<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\SecurityPrice;
use App\Infrastructure\Support\MarketCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property-read int $id
 * @property string $name
 * @property AssetType $type
 * @property-read ?int $total_quantity
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
abstract class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Asset\Models\AssetFactory> */
    use HasFactory;

    protected $table = 'securities';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SecurityPrice::class, 'security_id');
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class, 'security_id')->latestOfMany('date');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class, 'security_id')
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->latestOfMany('date');
    }

    public function todayPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class, 'security_id')->whereDate('date', today());
    }

    public function currentValuation(): float
    {
        $close = $this->latestPrice?->close;

        if ($close === null || $this->total_quantity === null) {
            return 0.0;
        }

        return (float) $this->total_quantity * (float) $close;
    }

    /** @param Builder<self> $query */
    public function scopeForAuth(Builder $query): void
    {
        $query
            ->select([
                'securities.id',
                'securities.type',
                'securities.name',
                DB::raw("SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE -transactions.quantity END) as total_quantity"),
                DB::raw("1.0 * SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity * transactions.unit_price ELSE 0 END) / NULLIF(SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE 0 END), 0) as pru"),
                DB::raw('SUM(transactions.fees) as total_fees'),
                DB::raw("SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE -transactions.quantity END) * (1.0 * SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity * transactions.unit_price ELSE 0 END) / NULLIF(SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE 0 END), 0)) + SUM(transactions.fees) as total_invested"),
                DB::raw('SUM(COALESCE(transactions.realized_gain, 0)) as total_realized_gain'),
            ])
            ->join('transactions', 'transactions.security_id', '=', 'securities.id')
            ->where('transactions.user_id', auth()->id())
            ->groupBy('securities.id', 'securities.type', 'securities.name');
    }

    /** @param Builder<self> $query */
    public function scopeForWallet(Builder $query, Wallet $wallet): void
    {
        $query
            ->select([
                'securities.id',
                'securities.type',
                'securities.name',
                DB::raw("SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE -transactions.quantity END) as total_quantity"),
                DB::raw("1.0 * SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity * transactions.unit_price ELSE 0 END) / NULLIF(SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE 0 END), 0) as pru"),
                DB::raw('SUM(transactions.fees) as total_fees'),
                DB::raw("SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE -transactions.quantity END) * (1.0 * SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity * transactions.unit_price ELSE 0 END) / NULLIF(SUM(CASE WHEN transactions.type = 'buy' THEN transactions.quantity ELSE 0 END), 0)) + SUM(transactions.fees) as total_invested"),
                DB::raw('SUM(COALESCE(transactions.realized_gain, 0)) as total_realized_gain'),
            ])
            ->join('transactions', 'transactions.security_id', '=', 'securities.id')
            ->where('transactions.wallet_id', $wallet->id)
            ->groupBy('securities.id', 'securities.type', 'securities.name');
    }
}
