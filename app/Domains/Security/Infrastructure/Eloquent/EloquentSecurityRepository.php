<?php

namespace App\Domains\Security\Infrastructure\Eloquent;

use App\Domains\Security\Contracts\SecurityRepositoryInterface;
use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Collection;

class EloquentSecurityRepository implements SecurityRepositoryInterface
{
    public function findById(int $id): ?Security
    {
        return Security::query()->find($id);
    }

    public function findByIsin(string $isin): ?Security
    {
        return Security::query()
            ->where('isin', $isin)
            ->first();
    }

    public function all(): Collection
    {
        return Security::query()->get();
    }

    public function search(string $query): Collection
    {
        return Security::query()
            ->where('isin', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->get();
    }

    public function withTransactions(): Collection
    {
        return Security::query()
            ->whereHas('transactions')
            ->get();
    }

    public function neededSectorUpdate(): Collection
    {
        return Security::query()
            ->whereHas('transactions')
            ->where(function ($query): void {
                $query->whereDoesntHave('sectors')
                    ->orWhereHas('sectors', function ($q): void {
                        $q->where('updated_at', '<', now()->subDays(7));
                    });
            })
            ->get();
    }

    public function forWallet(int $walletId): Collection
    {
        $wallet = \App\Domains\Portfolio\Models\Wallet::find($walletId);

        if (! $wallet) {
            return collect();
        }

        return Security::query()
            ->forWallet($wallet)
            ->with('latestPrice')
            ->get();
    }

    /**
     * @return array<int>
     */
    public function getIdsForWallet(int $walletId): array
    {
        $wallet = \App\Domains\Portfolio\Models\Wallet::find($walletId);

        if (! $wallet) {
            return [];
        }

        return Security::query()
            ->forWallet($wallet)
            ->pluck('securities.id')
            ->all();
    }

    public function save(Security $security): void
    {
        $security->save();
    }
}
