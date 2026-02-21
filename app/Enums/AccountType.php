<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum AccountType: string implements HasColor, HasIcon, HasLabel
{
    case Pea = 'pea';
    case Cto = 'cto';
    case Livret = 'livret';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Pea => 'PEA',
            self::Cto => 'CTO',
            self::Livret => 'Livret',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pea => 'success',
            self::Cto => 'warning',
            self::Livret => 'info',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Pea => Heroicon::OutlinedChartBar,
            self::Cto => Heroicon::OutlinedBuildingLibrary,
            self::Livret => Heroicon::OutlinedBanknotes,
        };
    }
}
