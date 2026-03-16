<?php

namespace App\Services;

use App\Models\Security;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;

class DashboardDataProvider
{
    /** @var array<string, Collection<int, Security>> */
    private array $securitiesByWallet = [];

    /**
     * @return Collection<int, Security>
     */
    public function securitiesForWallet(Wallet $wallet): Collection
    {
        $key = (string) $wallet->id;

        return $this->securitiesByWallet[$key] ??= Security::query()
            ->forWallet($wallet)
            ->with('latestPrice')
            ->get();
    }

    /**
     * @return Collection<int, Security>
     */
    public function allSecurities(): Collection
    {
        $all = new Collection;

        $wallets = Wallet::withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->get();

        foreach ($wallets as $wallet) {
            $all = $all->merge($this->securitiesForWallet($wallet));
        }

        return $all;
    }
}
