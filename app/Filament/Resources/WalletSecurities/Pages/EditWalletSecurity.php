<?php

namespace App\Filament\Resources\WalletSecurities\Pages;

use App\Filament\Pages\WalletPage;
use App\Filament\Resources\Securities\Pages\EditSecurity;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\WalletSecurities\WalletSecurityResource;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityGainStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationStatOverview;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

class EditWalletSecurity extends EditSecurity
{
    protected static string $resource = WalletSecurityResource::class;

    #[Url]
    public ?int $walletId = null;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('newTransaction')
                ->label('Nouvelle transaction')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->schema(fn (Schema $schema): Schema => TransactionForm::configure($schema, $this->walletId, $this->record->id))
                ->action(function (array $data): void {
                    Transaction::create([
                        ...$data,
                        'user_id' => auth()->id(),
                        'wallet_id' => $this->walletId,
                        'security_id' => $this->record->id,
                    ]);

                    Notification::make()
                        ->title('Transaction enregistrée')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            WalletPage::getUrl(['walletId' => $this->walletId]) => 'Portefeuille',
            $this->record->name,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return WalletPage::getUrl(['walletId' => $this->walletId]);
    }

    public function content(Schema $schema): Schema
    {
        $record = $this->record;
        $walletId = $this->walletId;

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
