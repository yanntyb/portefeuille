<?php

namespace App\Domains\Portfolio\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum FeeScope: string implements HasLabel
{
    case TotalValuation = 'total_valuation';
    case UnrealizedGain = 'unrealized_gain';
    case RealizedGain = 'realized_gain';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::TotalValuation => 'Valeur totale',
            self::UnrealizedGain => 'Plus-value latente',
            self::RealizedGain => 'Plus-value réalisée',
        };
    }
}
