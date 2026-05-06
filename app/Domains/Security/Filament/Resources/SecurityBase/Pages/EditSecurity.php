<?php

namespace App\Domains\Security\Filament\Resources\SecurityBase\Pages;

use App\Domains\Security\Filament\Resources\SecurityBase\Schemas\SecurityForm;
use App\Domains\Security\Filament\Widgets\SectorAllocationChartWidget;
use App\Domains\Security\Filament\Widgets\SingleSecurityGainStatsOverview;
use App\Domains\Security\Filament\Widgets\SingleSecurityPerformanceStatsOverview;
use App\Domains\Security\Filament\Widgets\SingleSecurityPriceChartWidget;
use App\Domains\Security\Filament\Widgets\SingleSecurityValuationChartWidget;
use App\Domains\Security\Filament\Widgets\SingleSecurityValuationStatOverview;
use App\Domains\Security\Jobs\UpdateSecurityJob;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

abstract class EditSecurity extends EditRecord
{
    protected string $view = 'filament.resources.securities.pages.edit-security';

    public bool $isUpdating = false;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? $this->record->ticker ?? $this->record->isin ?? 'Titre';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->isUpdating = Cache::has(UpdateSecurityJob::cacheKeyFor($this->record->id));
    }

    public function checkUpdateStatus(): void
    {
        $wasUpdating = $this->isUpdating;
        $this->isUpdating = Cache::has(UpdateSecurityJob::cacheKeyFor($this->record->id));

        if ($wasUpdating && ! $this->isUpdating) {
            $this->record->refresh();
            $this->fillForm();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editSecurity')
                ->label('Modifier')
                ->icon(Heroicon::PencilSquare)
                ->color('gray')
                ->fillForm(fn (): array => [
                    'isin' => $this->record->isin,
                    'name' => $this->record->name,
                    'ticker' => $this->record->ticker,
                ])
                ->schema([
                    TextInput::make('isin')
                        ->label('ISIN')
                        ->required()
                        ->maxLength(12),
                    TextInput::make('name')
                        ->label('Nom')
                        ->maxLength(255),
                    TextInput::make('ticker')
                        ->label('Ticker'),
                ])
                ->action(function (array $data): void {
                    $this->record->update($data);
                    $this->fillForm();

                    Notification::make()
                        ->title('Titre mis à jour')
                        ->success()
                        ->send();
                }),
            SecurityForm::updateFromIsinAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getWalletId(): ?int
    {
        return null;
    }

    public function content(Schema $schema): Schema
    {
        $record = $this->record;
        $walletId = $this->getWalletId();

        $components = [
            Livewire::make(SingleSecurityValuationStatOverview::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-valuation-stat'),
            Livewire::make(SingleSecurityValuationChartWidget::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-valuation-chart'),
            Livewire::make(SingleSecurityPerformanceStatsOverview::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-performance-stats'),
            Livewire::make(SingleSecurityGainStatsOverview::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-gain-stats'),
            Livewire::make(SingleSecurityPriceChartWidget::class, [
                'record' => $record,
            ])->key('single-security-price-chart'),
            Livewire::make(SectorAllocationChartWidget::class, [
                'record' => $record,
                'bareView' => true,
            ])->key('single-security-sector-allocation'),
            Section::make('Transactions')
                ->collapsible()
                ->collapsed()
                ->persistCollapsed()
                ->id('transactions')
                ->schema([
                    $this->getRelationManagersContentComponent(),
                ]),
        ];

        return $schema->components($components);
    }
}
