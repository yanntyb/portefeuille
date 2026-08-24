<?php

namespace App\Contexts\Market\Enums;

enum InstrumentType: string
{
    case Stock = 'stock';
    case ETF = 'etf';
    case Crypto = 'crypto';
    case Bond = 'bond';
    case Commodity = 'commodity';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Stock => 'Action',
            self::ETF => 'ETF',
            self::Crypto => 'Cryptomonnaie',
            self::Bond => 'Obligation',
            self::Commodity => 'Matière première',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stock => 'blue',
            self::ETF => 'cyan',
            self::Crypto => 'yellow',
            self::Bond => 'violet',
            self::Commodity => 'amber',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Stock => 'heroicon-o-chart-bar',
            self::ETF => 'heroicon-o-squares-2x2',
            self::Crypto => 'heroicon-o-currency-bitcoin',
            self::Bond => 'heroicon-o-document-text',
            self::Commodity => 'heroicon-o-cube',
        };
    }
}
