<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Asset\Models\Asset;
use App\Domains\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

readonly class AssetQueryService
{
    /** @return Builder<Asset> */
    public function forAuthenticatedUser(): Builder
    {
        return Asset::query()
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
            ->join('transactions', 'transactions.asset_id', '=', 'securities.id')
            ->where('transactions.user_id', auth()->id())
            ->groupBy('securities.id', 'securities.type', 'securities.name');
    }

    /** @return Builder<Asset> */
    public function forWallet(Wallet $wallet): Builder
    {
        return Asset::query()
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
            ->join('transactions', 'transactions.asset_id', '=', 'securities.id')
            ->where('transactions.wallet_id', $wallet->id)
            ->groupBy('securities.id', 'securities.type', 'securities.name');
    }
}
