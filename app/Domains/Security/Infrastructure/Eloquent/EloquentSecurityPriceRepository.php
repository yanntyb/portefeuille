<?php

namespace App\Domains\Security\Infrastructure\Eloquent;

use App\Domains\Security\Contracts\SecurityPriceRepositoryInterface;
use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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

    public function getLatestDateForSecurities(array $securityIds): SupportCollection
    {
        return SecurityPrice::query()
            ->selectRaw('security_id, MAX(date) as latest_date')
            ->whereIn('security_id', $securityIds)
            ->groupBy('security_id')
            ->pluck('latest_date', 'security_id');
    }

    public function getEarliestDateForSecurities(array $securityIds): SupportCollection
    {
        return SecurityPrice::query()
            ->selectRaw('security_id, MIN(date) as earliest_date')
            ->whereIn('security_id', $securityIds)
            ->groupBy('security_id')
            ->pluck('earliest_date', 'security_id');
    }

    public function findBySecurityAndDate(int $securityId, string $date): ?SecurityPrice
    {
        return SecurityPrice::query()
            ->where('security_id', $securityId)
            ->where('date', $date)
            ->first();
    }
}
