<?php

namespace App\Contexts\Valuation\Enums;

use Illuminate\Support\Carbon;

enum ValuationGranularity: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function bucketKey(string $ymd): string
    {
        return match ($this) {
            self::Day => $ymd,
            self::Week => Carbon::parse($ymd)->format('o-W'),
            self::Month => substr($ymd, 0, 7),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Day => 'Jour',
            self::Week => 'Sem',
            self::Month => 'Mois',
        };
    }
}
