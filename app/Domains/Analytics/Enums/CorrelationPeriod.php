<?php

namespace App\Domains\Analytics\Enums;

use Illuminate\Support\Carbon;

enum CorrelationPeriod: string
{
    case OneMonth = '1m';
    case ThreeMonths = '3m';
    case SixMonths = '6m';
    case OneYear = '1y';
    case Max = 'max';

    public function getLabel(): string
    {
        return match ($this) {
            self::OneMonth => '1 mois',
            self::ThreeMonths => '3 mois',
            self::SixMonths => '6 mois',
            self::OneYear => '1 an',
            self::Max => 'Max',
        };
    }

    public function startDate(): ?Carbon
    {
        return match ($this) {
            self::OneMonth => now()->subMonth(),
            self::ThreeMonths => now()->subMonths(3),
            self::SixMonths => now()->subMonths(6),
            self::OneYear => now()->subYear(),
            self::Max => null,
        };
    }
}
