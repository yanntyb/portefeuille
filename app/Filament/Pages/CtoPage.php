<?php

namespace App\Filament\Pages;

use App\Enums\AccountType;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CtoPage extends AccountPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'CTO';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'cto';

    public static function accountType(): AccountType
    {
        return AccountType::Cto;
    }
}
