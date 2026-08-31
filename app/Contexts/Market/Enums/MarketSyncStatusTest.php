<?php

use App\Contexts\Market\Enums\MarketSyncStatus;

it('is busy while queued or running, and only then', function () {
    expect(MarketSyncStatus::Queued->isBusy())->toBeTrue()
        ->and(MarketSyncStatus::Running->isBusy())->toBeTrue()
        ->and(MarketSyncStatus::Idle->isBusy())->toBeFalse()
        ->and(MarketSyncStatus::Succeeded->isBusy())->toBeFalse()
        ->and(MarketSyncStatus::Failed->isBusy())->toBeFalse();
});
