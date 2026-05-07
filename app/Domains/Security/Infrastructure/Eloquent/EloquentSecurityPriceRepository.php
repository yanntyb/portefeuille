<?php

namespace App\Domains\Security\Infrastructure\Eloquent;

use App\Domains\Security\Contracts\SecurityPriceRepositoryInterface;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Database\Eloquent\Collection;

class EloquentSecurityPriceRepository implements SecurityPriceRepositoryInterface
{
    public function findLatestForSecurity(int $securityId): ?SecurityPrice
    {
        return SecurityPrice::query()
            ->where('security_id', $securityId)
            ->orderByDesc('date')
            ->first();
    }

    public function findForSecurityOnDate(int $securityId, \DateTimeInterface $date): ?SecurityPrice
    {
        return SecurityPrice::query()
            ->where('security_id', $securityId)
            ->whereDate('date', $date)
            ->first();
    }

    public function forSecuritySince(int $securityId, \DateTimeInterface $date): Collection
    {
        return SecurityPrice::query()
            ->where('security_id', $securityId)
            ->where('date', '>=', $date)
            ->orderBy('date')
            ->get();
    }

    public function save(SecurityPrice $price): void
    {
        $price->save();
    }
}
