<?php

namespace App\Contexts\Portfolio\Enums;

enum TransactionType: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }
}
