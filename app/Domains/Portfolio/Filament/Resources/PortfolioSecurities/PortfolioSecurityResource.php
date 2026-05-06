<?php

namespace App\Domains\Portfolio\Filament\Resources\PortfolioSecurities;

use App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\Pages\CreatePortfolioSecurity;
use App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\Pages\EditPortfolioSecurity;
use App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\Pages\ListPortfolioSecurities;
use App\Domains\Security\Filament\Resources\SecurityBase\Schemas\SecurityForm;
use App\Domains\Security\Filament\Resources\SecurityBase\Tables\SecuritiesTable;
use App\Domains\Security\Models\Security;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PortfolioSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $slug = 'portefeuille';

    protected static ?string $modelLabel = 'titre';

    protected static ?string $pluralModelLabel = 'titres';

    protected static ?string $navigationLabel = 'Titres';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecuritiesTable::configure($table)
            ->recordActions([])
            ->modifyQueryUsing(fn (Builder $query) => $query->forAuth());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortfolioSecurities::route('/'),
            'create' => CreatePortfolioSecurity::route('/create'),
            'edit' => EditPortfolioSecurity::route('/{record}/edit'),
        ];
    }
}
