<?php

namespace App\Domains\Asset\Infrastructure\Eloquent;

use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Asset;
use App\Domains\Asset\Models\Stock;
use App\Domains\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;

class EloquentAssetRepository implements AssetRepositoryInterface
{
    public function findById(int $id): ?Asset
    {
        return Stock::query()->find($id);
    }

    public function forWallet(int $walletId): Collection
    {
        $wallet = Wallet::find($walletId);

        if (! $wallet) {
            return collect();
        }

        return Stock::query()
            ->forWallet($wallet)
            ->with('latestPrice')
            ->get();
    }

    public function getIdsForWallet(int $walletId): array
    {
        $wallet = Wallet::find($walletId);

        if (! $wallet) {
            return [];
        }

        return Stock::query()
            ->forWallet($wallet)
            ->pluck('securities.id')
            ->all();
    }

    public function findByType(AssetType $type): Collection
    {
        return Stock::query()
            ->where('type', $type)
            ->get();
    }

    public function save(Asset $asset): void
    {
        $asset->save();
    }
}
