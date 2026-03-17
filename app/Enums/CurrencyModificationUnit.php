<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CurrencyModificationUnit: string implements HasLabel
{
    case Percentage = 'percentage';
    case Currency = 'currency';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Percentage => '%',
            self::Currency => '€',
        };
    }
}
