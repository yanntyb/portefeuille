<?php

namespace App\Infrastructure\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class MarketCalendar
{
    public static function lastTradingDate(): CarbonInterface
    {
        $today = today();

        return match ($today->dayOfWeek) {
            Carbon::SATURDAY => $today->subDay(),
            Carbon::SUNDAY => $today->subDays(2),
            default => $today,
        };
    }
}
