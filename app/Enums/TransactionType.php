<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum TransactionType: string implements HasColor, HasIcon, HasLabel
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Buy => 'success',
            self::Sell => 'danger',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Buy => Heroicon::OutlinedArrowTrendingUp,
            self::Sell => Heroicon::OutlinedArrowTrendingDown,
        };
    }
}
