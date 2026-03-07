<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Security;
use Illuminate\Database\Eloquent\Collection;

class DashboardDataProvider
{
    /** @var array<string, Collection<int, Security>> */
    private array $securitiesByAccount = [];

    /**
     * @return Collection<int, Security>
     */
    public function securitiesForAccount(AccountType $accountType): Collection
    {
        $key = $accountType->value;

        return $this->securitiesByAccount[$key] ??= Security::query()
            ->forAccountType($accountType, auth()->id())
            ->with('latestPrice')
            ->get();
    }

    /**
     * @return Collection<int, Security>
     */
    public function allSecurities(): Collection
    {
        $all = new Collection;

        foreach ([AccountType::Pea, AccountType::Cto] as $accountType) {
            $all = $all->merge($this->securitiesForAccount($accountType));
        }

        return $all;
    }
}
