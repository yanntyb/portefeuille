<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Security extends Model
{
    /** @use HasFactory<\Database\Factories\SecurityFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'isin',
        'name',
        'ticker',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SecurityPrice::class);
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(SecuritySector::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class)->latestOfMany('date');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class)
            ->where('date', '>=', today()->subDays(4))
            ->latestOfMany('date');
    }

    public function todayPrice(): HasOne
    {
        return $this->hasOne(SecurityPrice::class)->whereDate('date', today());
    }

    /** @param Builder<self> $query */
    public function scopeForAccountType(Builder $query, AccountType $accountType, int $userId): void
    {
        $query
            ->select([
                'securities.id',
                'securities.isin',
                'securities.name',
                DB::raw('SUM(transactions.quantity) as total_quantity'),
                DB::raw('SUM(transactions.quantity * transactions.unit_price) / SUM(transactions.quantity) as pru'),
                DB::raw('SUM(transactions.fees) as total_fees'),
                DB::raw('SUM(transactions.quantity * transactions.unit_price) + SUM(transactions.fees) as total_invested'),
            ])
            ->join('transactions', 'transactions.security_id', '=', 'securities.id')
            ->where('transactions.account_type', $accountType->value)
            ->where('transactions.user_id', $userId)
            ->groupBy('securities.id', 'securities.isin', 'securities.name');
    }
}
