<?php

namespace App\Domains\Portfolio\Infrastructure\Eloquent;

use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function findById(int $id): ?Transaction
    {
        return Transaction::query()->find($id);
    }

    public function findByIdForUser(int $id, int $userId): ?Transaction
    {
        return Transaction::query()
            ->forUser($userId)
            ->find($id);
    }

    public function forUser(int $userId): Collection
    {
        return Transaction::query()
            ->forUser($userId)
            ->get();
    }

    public function forWallet(int $walletId, int $userId): Collection
    {
        return Transaction::query()
            ->forUser($userId)
            ->where('wallet_id', $walletId)
            ->get();
    }

    public function forSecurity(int $securityId, int $userId): Collection
    {
        return Transaction::query()
            ->forUser($userId)
            ->where('asset_id', $securityId)
            ->get();
    }

    public function forSecurities(array $securityIds, int $userId): Collection
    {
        return Transaction::query()
            ->forUser($userId)
            ->whereIn('asset_id', $securityIds)
            ->orderBy('date')
            ->get();
    }

    public function save(Transaction $transaction): void
    {
        $transaction->save();
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }

    public function getFirstBuyDateForWallet(int $walletId): ?\Illuminate\Support\Carbon
    {
        $date = \App\Domains\Portfolio\Models\Transaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', 'buy')
            ->min('date');

        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }
}
