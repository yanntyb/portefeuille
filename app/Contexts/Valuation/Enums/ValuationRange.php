<?php

namespace App\Contexts\Valuation\Enums;

enum ValuationRange: string
{
    case OneMonth = '1M';
    case SixMonths = '6M';
    case OneYear = '1Y';
    case Max = 'max';

    public function months(): ?int
    {
        return match ($this) {
            self::OneMonth => 1,
            self::SixMonths => 6,
            self::OneYear => 12,
            self::Max => null,
        };
    }
}
