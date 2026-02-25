<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

abstract class EditSecurity extends EditRecord
{
    public function getHeading(): string
    {
        return $this->record->name ?? parent::getHeading();
    }

    protected function getHeaderActions(): array
    {
        return [
            SecurityForm::updateFromIsinAction(),
            $this->reloadAllPricesAction(),
        ];
    }

    private function reloadAllPricesAction(): Action
    {
        return Action::make('reloadAllPrices')
            ->label('Recharger tous les prix')
            ->icon(Heroicon::ArrowPathRoundedSquare)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Recharger tous les prix')
            ->modalDescription('Tous les prix existants seront supprimés et rechargés depuis Yahoo Finance. Cette opération peut prendre quelques secondes.')
            ->action(function (): void {
                /** @var Security $security */
                $security = $this->record;

                $security->prices()->delete();

                $count = app(YahooFinanceService::class)
                    ->fetchAndStorePrices($security, new \DateTimeImmutable('-5 years'));

                Notification::make()
                    ->title("{$count} prix rechargés avec succès")
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderWidgets(): array
    {
        $resource = static::getResource();
        $isAccountResource = is_subclass_of($resource, AccountSecurityResource::class);

        $accountType = $isAccountResource
            ? $resource::accountType()->value
            : null;

        $widgets = [
            SingleSecurityStatsOverview::make([
                'accountType' => $accountType,
            ]),
            SingleSecurityPriceChartWidget::make(),
        ];

        if ($isAccountResource) {
            $widgets[] = SingleSecurityValuationChartWidget::make(['accountType' => $accountType]);
        }

        $widgets[] = SectorAllocationChartWidget::make();

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}
