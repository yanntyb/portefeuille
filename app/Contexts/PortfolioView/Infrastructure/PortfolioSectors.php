<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\PortfolioView\Datas\SectorSliceData;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;

class PortfolioSectors implements SectorBreakdownPort
{
    public function __construct(private GetSectorBreakdown $breakdown) {}

    /** @return list<SectorSliceData> */
    public function breakdownFor(int $userId, HoldingScope $scope): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return array_map(
            fn (AllocationSliceData $slice): SectorSliceData => new SectorSliceData(
                label: $slice->label,
                value: $slice->value,
                pct: $slice->pct,
                color: $slice->color,
            ),
            ($this->breakdown)($user, $scope),
        );
    }
}
