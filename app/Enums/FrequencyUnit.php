<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum FrequencyUnit: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Monthly => 'Mensuel',
            self::Quarterly => 'Trimestriel',
            self::Yearly => 'Annuel',
        };
    }
}
