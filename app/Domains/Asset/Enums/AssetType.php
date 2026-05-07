<?php

namespace App\Domains\Asset\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AssetType: string implements HasColor, HasIcon, HasLabel
{
    case Stock = 'stock';
    case ETF = 'etf';
    case Crypto = 'crypto';
    case RealEstate = 'real_estate';
    case Bond = 'bond';
    case Savings = 'savings';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stock => 'Stock',
            self::ETF => 'ETF',
            self::Crypto => 'Cryptocurrency',
            self::RealEstate => 'Real Estate',
            self::Bond => 'Bond',
            self::Savings => 'Savings Account',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stock => 'blue',
            self::ETF => 'cyan',
            self::Crypto => 'yellow',
            self::RealEstate => 'amber',
            self::Bond => 'violet',
            self::Savings => 'emerald',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Stock => 'heroicon-o-chart-bar',
            self::ETF => 'heroicon-o-squares-2x2',
            self::Crypto => 'heroicon-o-currency-bitcoin',
            self::RealEstate => 'heroicon-o-home',
            self::Bond => 'heroicon-o-document-text',
            self::Savings => 'heroicon-o-building-library',
        };
    }
}
