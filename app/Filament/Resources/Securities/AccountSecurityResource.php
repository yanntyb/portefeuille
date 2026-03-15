<?php

namespace App\Filament\Resources\Securities;

use App\Enums\AccountType;
use App\Filament\Resources\Securities\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityFeesStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityPlusValueWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceStatsWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationStatsWidget;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

abstract class AccountSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    abstract public static function accountType(): AccountType;

    /** @return class-string<Page> */
    abstract public static function listPage(): string;

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
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
            SectorAllocationChartWidget::class,
            SingleSecurityPlusValueWidget::class,
            SingleSecurityValuationStatsWidget::class,
            SingleSecurityFeesStatsWidget::class,
            SingleSecurityPriceStatsWidget::class,
            SingleSecurityValuationChartWidget::class,
        ];
    }
}
