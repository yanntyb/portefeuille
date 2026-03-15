<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\AccountSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use App\Filament\Widgets\Securities\SectorAllocationChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityGainStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPerformanceStatsOverview;
use App\Filament\Widgets\Securities\SingleSecurityPriceChartWidget;
use App\Filament\Widgets\Securities\SingleSecurityValuationChartWidget;
use App\Jobs\UpdateSecurityJob;
use App\Models\Security;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

abstract class EditSecurity extends EditRecord
{
    protected string $view = 'filament.resources.securities.pages.edit-security';

    public bool $isUpdating = false;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString($this->record->name.' '.$this->getFormattedValuation());
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    private function getFormattedValuation(): string
    {
        $record = $this->record;
        $record->loadMissing('latestPrice');

        $close = $record->latestPrice?->close;

        if ($close === null) {
            return '';
        }

        $resource = static::getResource();
        $isAccountResource = is_subclass_of($resource, AccountSecurityResource::class);

        if ($isAccountResource) {
            $accountType = $resource::accountType();
            $security = Security::query()
                ->forAccountType($accountType, auth()->id())
                ->where('securities.id', $record->id)
                ->with('latestPrice')
                ->first();

            $totalQuantity = $security?->total_quantity;
            $totalInvested = $security?->total_invested;
        } else {
            $security = Security::query()
                ->forAuth()
                ->where('securities.id', $record->id)
                ->with('latestPrice')
                ->first();

            $totalQuantity = $security?->total_quantity;
            $totalInvested = $security?->total_invested;
        }

        if ($totalQuantity === null) {
            return '';
        }

        $valuation = (float) $totalQuantity * (float) $close;
        $isPositive = $valuation >= (float) ($totalInvested ?? 0);
        $colorClass = $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

        return '<span class="'.$colorClass.'">'.Number::currency($valuation, 'EUR').'</span>';
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

    public function content(Schema $schema): Schema
    {
        $resource = static::getResource();
        $isAccountResource = is_subclass_of($resource, AccountSecurityResource::class);
        $accountType = $isAccountResource ? $resource::accountType()->value : null;

        $record = $this->record;

        $components = [
            Livewire::make(SingleSecurityPerformanceStatsOverview::class, [
                'record' => $record,
                'accountType' => $accountType,
            ])->key('single-security-performance-stats'),
            Livewire::make(SingleSecurityGainStatsOverview::class, [
                'record' => $record,
                'accountType' => $accountType,
            ])->key('single-security-gain-stats'),
        ];

        if ($isAccountResource) {
            $components[] = Livewire::make(SingleSecurityValuationChartWidget::class, [
                'record' => $record,
                'accountType' => $accountType,
            ])->key('single-security-valuation-chart');
        }

        $components[] = Livewire::make(SingleSecurityPriceChartWidget::class, [
            'record' => $record,
        ])->key('single-security-price-chart');

        $components[] = Livewire::make(SectorAllocationChartWidget::class, [
            'record' => $record,
            'bareView' => true,
        ])->key('single-security-sector-allocation');

        $components[] = Section::make('Transactions')
            ->collapsible()
            ->collapsed()
            ->persistCollapsed()
            ->id('transactions')
            ->extraAttributes(['class' => 'fi-section-no-content-padding'])
            ->schema([
                $this->getRelationManagersContentComponent(),
            ]);

        return $schema->components($components);
    }
}
