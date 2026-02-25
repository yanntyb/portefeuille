<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum PerformancePeriod: string
{
    case OneMonth = '1m';
    case ThreeMonths = '3m';
    case SixMonths = '6m';
    case OneYear = '1y';
    case TwoYears = '2y';
    case ThreeYears = '3y';
    case FiveYears = '5y';

    public function getLabel(): string
    {
        return match ($this) {
            self::OneMonth => '1 mois',
            self::ThreeMonths => '3 mois',
            self::SixMonths => '6 mois',
            self::OneYear => '1 an',
            self::TwoYears => '2 ans',
            self::ThreeYears => '3 ans',
            self::FiveYears => '5 ans',
        };
    }

    public function startDate(): Carbon
    {
        return match ($this) {
            self::OneMonth => now()->subMonth(),
            self::ThreeMonths => now()->subMonths(3),
            self::SixMonths => now()->subMonths(6),
            self::OneYear => now()->subYear(),
            self::TwoYears => now()->subYears(2),
            self::ThreeYears => now()->subYears(3),
            self::FiveYears => now()->subYears(5),
        };
    }
}
