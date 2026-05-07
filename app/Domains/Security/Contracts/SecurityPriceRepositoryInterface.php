<?php

namespace App\Domains\Security\Contracts;

use App\Domains\Security\Models\SecurityPrice;
use Illuminate\Database\Eloquent\Collection;

interface SecurityPriceRepositoryInterface
{
    public function findLatestForSecurity(int $securityId): ?SecurityPrice;

    public function findForSecurityOnDate(int $securityId, \DateTimeInterface $date): ?SecurityPrice;

    public function forSecuritySince(int $securityId, \DateTimeInterface $date): Collection;

    public function save(SecurityPrice $price): void;
}
