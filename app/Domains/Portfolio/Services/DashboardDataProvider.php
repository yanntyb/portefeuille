<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Collection;

class DashboardDataProvider
{
    /** @var array<string, Collection<int, Security>> */
    private array $securitiesByWallet = [];

    /** @var Collection<int, Wallet>|null */
    private ?Collection $wallets = null;

    /**
     * @return Collection<int, Wallet>
     */
    public function wallets(): Collection
    {
        return $this->wallets ??= Wallet::withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->get();
    }

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

        foreach ($this->wallets() as $wallet) {
            $all = $all->merge($this->securitiesForWallet($wallet));
        }

        return $all;
    }
}
