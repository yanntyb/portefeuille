<?php

use App\Support\MarketCalendar;
use Carbon\Carbon;

it('returns today on weekdays', function (string $date) {
    Carbon::setTestNow($date);

    expect(MarketCalendar::lastTradingDate()->toDateString())->toBe($date);
})->with([
    'monday' => '2026-02-23',
    'tuesday' => '2026-02-24',
    'wednesday' => '2026-02-25',
    'thursday' => '2026-02-26',
    'friday' => '2026-02-27',
]);

it('returns friday on saturday', function () {
    Carbon::setTestNow('2026-02-28'); // Saturday

    expect(MarketCalendar::lastTradingDate()->toDateString())->toBe('2026-02-27');
});

it('returns friday on sunday', function () {
    Carbon::setTestNow('2026-03-01'); // Sunday

    expect(MarketCalendar::lastTradingDate()->toDateString())->toBe('2026-02-27');
});
