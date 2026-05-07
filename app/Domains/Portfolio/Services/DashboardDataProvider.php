<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Contracts\SecurityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DashboardDataProvider
{
    public function __construct(
        private SecurityRepositoryInterface $securityRepository,
    ) {}
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

        return $this->securitiesByWallet[$key] ??= $this->securityRepository->forWallet($wallet->id);
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
