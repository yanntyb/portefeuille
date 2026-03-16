<?php

namespace App\Filament\Resources\WalletSecurities\Pages;

use App\Filament\Pages\WalletPage;
use App\Filament\Resources\Securities\Pages\EditSecurity;
use App\Filament\Resources\WalletSecurities\WalletSecurityResource;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityGainStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Models\Security;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;

class EditWalletSecurity extends EditSecurity
{
    protected static string $resource = WalletSecurityResource::class;

    #[Url]
    public ?int $walletId = null;

    protected function getFormattedValuation(): string
    {
        $record = $this->record;
        $record->loadMissing('latestPrice');

        $close = $record->latestPrice?->close;

        if ($close === null || $this->walletId === null) {
            return '';
        }

        $security = Security::query()
            ->forWallet(\App\Models\Wallet::find($this->walletId))
            ->where('securities.id', $record->id)
            ->with('latestPrice')
            ->first();

        $totalQuantity = $security?->total_quantity;
        $totalInvested = $security?->total_invested;

        if ($totalQuantity === null) {
            return '';
        }

        $valuation = (float) $totalQuantity * (float) $close;
        $isPositive = $valuation >= (float) ($totalInvested ?? 0);
        $colorClass = $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

        return '<span class="'.$colorClass.'">'.Number::currency($valuation, 'EUR').'</span>';
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
            Livewire::make(SingleSecurityPerformanceStatsOverview::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-performance-stats'),
            Livewire::make(SingleSecurityGainStatsOverview::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-gain-stats'),
            Livewire::make(SingleSecurityValuationChartWidget::class, [
                'record' => $record,
                'walletId' => $walletId,
            ])->key('single-security-valuation-chart'),
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
