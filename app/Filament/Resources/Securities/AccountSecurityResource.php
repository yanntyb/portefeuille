<?php

namespace App\Filament\Resources\Securities;

use App\Enums\AccountType;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Resources\Securities\Tables\SecuritiesTable;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class AccountSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    abstract public static function accountType(): AccountType;

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecuritiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->forAccountType(static::accountType(), auth()->id()));
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
            SingleSecurityStatsOverview::class,
            SingleSecurityValuationChartWidget::class,
        ];
    }
}
