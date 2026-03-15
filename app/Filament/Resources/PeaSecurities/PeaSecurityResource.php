<?php

namespace App\Filament\Resources\PeaSecurities;

use App\Enums\AccountType;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Resources\Securities\AccountSecurityResource;
use BackedEnum;
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

    public static function accountType(): AccountType
    {
        return AccountType::Pea;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeaSecurities::route('/'),
            'edit' => EditPeaSecurity::route('/{record}/edit'),
        ];
    }
}
