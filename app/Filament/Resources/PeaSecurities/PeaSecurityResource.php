<?php

namespace App\Filament\Resources\PeaSecurities;

use App\Enums\AccountType;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
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

class PeaSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $slug = 'pea';

    protected static ?string $modelLabel = 'titre PEA';

    protected static ?string $pluralModelLabel = 'titres PEA';

    protected static ?string $navigationLabel = 'PEA';

    protected static ?string $breadcrumb = 'PEA';

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecuritiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->forAccountType(AccountType::Pea, auth()->id()));
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
            'index' => ListPeaSecurities::route('/'),
            'edit' => EditPeaSecurity::route('/{record}/edit'),
        ];
    }
}
