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

    public function save(Security $security): void
    {
        $security->save();
    }
}
