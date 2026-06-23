<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Models\Price;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface PriceRepositoryContract
{
    public function latestForAsset(int $id): ?Price;

    public function forAssetOnDate(int $id, Carbon $date): ?Price;

    /** @return Collection<int, Price> */
    public function forAssetSince(int $id, Carbon $since): Collection;

    /**
     * @param  array<int>  $ids
     * @return Collection<int, Price>
     */
    public function forAssets(array $ids, Carbon $since): Collection;

    /**
     * @param  array<int>  $ids
     * @return array<int>
     */
    public function filterAssetIdsHavingPriceSince(array $ids, Carbon $since): array;
}
