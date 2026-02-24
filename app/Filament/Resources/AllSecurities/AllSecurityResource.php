<?php

namespace App\Filament\Resources\AllSecurities;

use App\Filament\Resources\AllSecurities\Pages\EditAllSecurity;
use App\Filament\Resources\AllSecurities\Pages\ListAllSecurities;
use App\Filament\Resources\Securities\RelationManagers\PricesRelationManager;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Models\Security;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AllSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $slug = 'securities';

    protected static ?string $modelLabel = 'titre';

    protected static ?string $pluralModelLabel = 'titres';

    protected static ?string $navigationLabel = 'Titres';

    protected static ?string $breadcrumb = 'Titres';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

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
        return $table
            ->columns([
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user_transactions_count')
                    ->label('Transactions')
                    ->sortable(),
                TextColumn::make('latestPrice.close')
                    ->label('Dernier prix')
                    ->money('eur')
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'transactions as user_transactions_count',
            ]))
            ->recordActions([
                SecurityForm::updateFromIsinTableAction(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllSecurities::route('/'),
            'edit' => EditAllSecurity::route('/{record}/edit'),
        ];
    }
}
