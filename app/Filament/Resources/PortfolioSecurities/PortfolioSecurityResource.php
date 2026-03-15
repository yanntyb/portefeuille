<?php

namespace App\Filament\Resources\PortfolioSecurities;

use App\Filament\Resources\PortfolioSecurities\Pages\ListPortfolioSecurities;
use App\Filament\Resources\Securities\Tables\SecuritiesTable;
use App\Models\Security;
use BackedEnum;
use Filament\Resources\Resource;
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
        ];
    }
}
