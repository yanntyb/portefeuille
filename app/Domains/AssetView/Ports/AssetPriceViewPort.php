<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\ValueObjects\PriceHistoryDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AssetPriceViewPort
{
    /** @return Collection<int, PriceHistoryDTO> */
    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection;

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO;
}
