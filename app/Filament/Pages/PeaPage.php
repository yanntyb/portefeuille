<?php

namespace App\Filament\Pages;

use App\Enums\AccountType;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PeaPage extends AccountPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'PEA';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pea';

    public static function accountType(): AccountType
    {
        return AccountType::Pea;
    }
}
