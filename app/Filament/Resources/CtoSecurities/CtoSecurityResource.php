<?php

namespace App\Filament\Resources\CtoSecurities;

use App\Enums\AccountType;
use App\Filament\Resources\CtoSecurities\Pages\CreateCtoSecurity;
use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Resources\Securities\Tables\SecuritiesTable;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CtoSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $slug = 'cto';

    protected static ?string $modelLabel = 'titre CTO';

    protected static ?string $pluralModelLabel = 'titres CTO';

    protected static ?string $navigationLabel = 'CTO';

    protected static ?string $breadcrumb = 'CTO';

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecuritiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->forAccountType(AccountType::Cto));
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            SecurityStatsOverview::class,
            ValuationChartWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCtoSecurities::route('/'),
            'create' => CreateCtoSecurity::route('/create'),
            'edit' => EditCtoSecurity::route('/{record}/edit'),
        ];
    }
}
