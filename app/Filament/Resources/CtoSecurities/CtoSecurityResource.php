<?php

namespace App\Filament\Resources\CtoSecurities;

use App\Enums\AccountType;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\Securities\AccountSecurityResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class CtoSecurityResource extends AccountSecurityResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $slug = 'cto';

    protected static ?string $modelLabel = 'titre CTO';

    protected static ?string $pluralModelLabel = 'titres CTO';

    protected static ?string $navigationLabel = 'CTO';

    protected static ?string $breadcrumb = 'CTO';

    public static function accountType(): AccountType
    {
        return AccountType::Cto;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCtoSecurities::route('/'),
            'edit' => EditCtoSecurity::route('/{record}/edit'),
        ];
    }
}
