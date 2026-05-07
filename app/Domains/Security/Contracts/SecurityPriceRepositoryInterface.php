<?php

namespace App\Domains\Security\Contracts;

use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface SecurityPriceRepositoryInterface
{
    public function findLatestForSecurity(int $securityId): ?SecurityPrice;

    public function findForSecurityOnDate(int $securityId, \DateTimeInterface $date): ?SecurityPrice;

    public function forSecuritySince(int $securityId, \DateTimeInterface $date): Collection;

    public function save(SecurityPrice $price): void;

    public function getLatestDateForSecurities(array $securityIds): SupportCollection;

    public function getEarliestDateForSecurities(array $securityIds): SupportCollection;

    public function findBySecurityAndDate(int $securityId, string $date): ?SecurityPrice;

    public function getForSecurities(array $securityIds): Collection;

    /**
     * @param  array<int>  $securityIds
     * @return array<int>
     */
    public function getSecurityIdsWithRecentPrice(array $securityIds, string $fromDate): array;
}
