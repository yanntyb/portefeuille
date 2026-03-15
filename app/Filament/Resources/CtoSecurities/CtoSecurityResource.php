<?php

namespace App\Filament\Resources\CtoSecurities;

use App\Enums\AccountType;
use App\Filament\Pages\CtoPage;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\Securities\AccountSecurityResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CtoSecurityResource extends AccountSecurityResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $slug = 'cto';

    protected static ?string $modelLabel = 'titre CTO';

    protected static ?string $pluralModelLabel = 'titres CTO';

    protected static ?string $navigationLabel = 'CTO';

    protected static ?string $breadcrumb = 'CTO';

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function accountType(): AccountType
    {
        return AccountType::Cto;
    }

    /** @return class-string<Page> */
    public static function listPage(): string
    {
        return CtoPage::class;
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditCtoSecurity::route('/{record}/edit'),
        ];
    }
}
