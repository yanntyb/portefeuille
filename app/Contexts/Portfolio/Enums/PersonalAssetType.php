<?php

namespace App\Contexts\Portfolio\Enums;

enum PersonalAssetType: string
{
    case RealEstate = 'real_estate';
    case Savings = 'savings';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::RealEstate => 'Real Estate',
            self::Savings => 'Savings Account',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RealEstate => 'amber',
            self::Savings => 'emerald',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::RealEstate => 'heroicon-o-home',
            self::Savings => 'heroicon-o-building-library',
        };
    }
}
