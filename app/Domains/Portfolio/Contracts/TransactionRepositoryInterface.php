<?php

namespace App\Domains\Portfolio\Contracts;

use App\Domains\Portfolio\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

interface TransactionRepositoryInterface
{
    public function findById(int $id): ?Transaction;

    public function findByIdForUser(int $id, int $userId): ?Transaction;

    public function forUser(int $userId): Collection;

    public function forWallet(int $walletId, int $userId): Collection;

    public function forSecurity(int $securityId, int $userId): Collection;

    public function forSecurities(array $securityIds, int $userId): Collection;

    public function save(Transaction $transaction): void;

    public function delete(Transaction $transaction): void;

    public function getFirstBuyDateForWallet(int $walletId): ?\Illuminate\Support\Carbon;
}
