<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\HoldingsPort;

class PortfolioHoldings implements HoldingsPort
{
    public function __construct(private GetPortfolioOverview $overview) {}

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user);

        return new ClassSnapshotData(value: $overview->totalValue, invested: $overview->totalCost);
    }
}
