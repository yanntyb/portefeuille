<?php

use App\Contexts\Income\Services\RollingWindow;
use Illuminate\Support\Carbon;

it('remonte au même jour un an plus tôt, à minuit', function () {
    expect((new RollingWindow)->slidingDays(Carbon::parse('2026-08-29 15:30:00'))->toDateTimeString())
        ->toBe('2025-08-29 00:00:00');
});
