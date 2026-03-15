<?php

namespace App\Filament\Resources\PeaSecurities;

use App\Enums\AccountType;
use App\Filament\Pages\PeaPage;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\Securities\AccountSecurityResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PeaSecurityResource extends AccountSecurityResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $slug = 'pea';

    protected static ?string $modelLabel = 'titre PEA';

    protected static ?string $pluralModelLabel = 'titres PEA';

    protected static ?string $navigationLabel = 'PEA';

    protected static ?string $breadcrumb = 'PEA';

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public static function accountType(): AccountType
    {
        return AccountType::Pea;
    }

    /** @return class-string<Page> */
    public static function listPage(): string
    {
        return PeaPage::class;
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditPeaSecurity::route('/{record}/edit'),
        ];
    }
}
