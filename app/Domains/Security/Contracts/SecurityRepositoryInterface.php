<?php

namespace App\Domains\Security\Contracts;

use App\Domains\Security\Models\Security;
use Illuminate\Database\Eloquent\Collection;

interface SecurityRepositoryInterface
{
    public function findById(int $id): ?Security;

    public function findByIsin(string $isin): ?Security;

    public function all(): Collection;

    public function search(string $query): Collection;

    public function withTransactions(): Collection;

    public function neededSectorUpdate(): Collection;

    public function forWallet(int $walletId): Collection;

    public function save(Security $security): void;
}
