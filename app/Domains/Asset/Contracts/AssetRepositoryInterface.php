<?php

namespace App\Domains\Asset\Contracts;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Collection;

interface AssetRepositoryInterface
{
    public function findById(int $id): ?Asset;

    /** @return Collection<int, Asset> */
    public function forWallet(int $walletId): Collection;

    /** @return array<int> */
    public function getIdsForWallet(int $walletId): array;

    /** @return Collection<int, Asset> */
    public function findByType(AssetType $type): Collection;

    public function save(Asset $asset): void;
}
