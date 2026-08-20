<?php

namespace App\Contexts\RealEstate\Enums;

enum ExpenseCategory: string
{
    case PropertyTax = 'property_tax';
    case CoOwnership = 'co_ownership';
    case Insurance = 'insurance';
    case Management = 'management';
    case Works = 'works';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PropertyTax => 'Taxe foncière',
            self::CoOwnership => 'Copropriété',
            self::Insurance => 'Assurance',
            self::Management => 'Gestion',
            self::Works => 'Travaux',
            self::Other => 'Autre',
        };
    }
}
