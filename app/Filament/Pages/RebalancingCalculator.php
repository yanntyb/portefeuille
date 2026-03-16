<?php

namespace App\Filament\Pages;

use App\Models\AllocationProfile;
use App\Models\Security;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\RebalancingCalculator as RebalancingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * @property-read Schema $form
 */
class RebalancingCalculator extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Rééquilibrage';

    protected static ?string $title = 'Calculateur de Rééquilibrage';

    protected static string|\UnitEnum|null $navigationGroup = 'Outils';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.rebalancing-calculator';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $resultItems = [];

    public float $totalInvested = 0;

    public float $remainder = 0;

    public bool $hasResults = false;

    public function mount(): void
    {
        $this->form->fill([
            'wallet_id' => null,
            'amount' => 500,
            'profile_id' => null,
            'allocations' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('wallet_id')
                        ->label('Type de compte')
                        ->options(fn (): array => [
                            '' => 'Global (tous comptes)',
                            ...Wallet::query()
                                ->withoutGlobalScope('user')
                                ->where('user_id', auth()->id())
                                ->pluck('name', 'id')
                                ->all(),
                        ])
                        ->live()
                        ->afterStateUpdated(fn () => $this->resetResults()),

                    TextInput::make('amount')
                        ->label('Montant à investir (€)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(500),

                    Select::make('profile_id')
                        ->label('Profil sauvegardé')
                        ->options(fn (): array => AllocationProfile::query()
                            ->where('user_id', auth()->id())
                            ->pluck('name', 'id')
                            ->all())
                        ->placeholder('Sélectionner un profil...')
                        ->live()
                        ->afterStateUpdated(fn (?string $state) => $this->loadProfile($state)),

                    Repeater::make('allocations')
                        ->label('Allocations cibles')
                        ->schema([
                            Select::make('security_id')
                                ->label('Titre')
                                ->options(fn (): array => Security::query()
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),

                            TextInput::make('target_percentage')
                                ->label('% cible')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter un titre')
                        ->reorderable(false),
                ])
                    ->livewireSubmitHandler('calculate')
                    ->footer([
                        Actions::make([
                            Action::make('calculate')
                                ->label('Calculer')
                                ->submit('calculate')
                                ->icon('heroicon-o-calculator'),
                            Action::make('save_profile')
                                ->label('Sauvegarder le profil')
                                ->icon('heroicon-o-bookmark')
                                ->action('saveProfile')
                                ->color('gray'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->resultItems)
            ->columns([
                TextColumn::make('name')
                    ->label('Titre')
                    ->weight(FontWeight::Medium),

                TextColumn::make('price')
                    ->label('Prix')
                    ->money('eur')
                    ->alignment(Alignment::End),

                TextColumn::make('quantity_held')
                    ->label('Qté détenue')
                    ->numeric(decimalPlaces: 2)
                    ->alignment(Alignment::End),

                TextColumn::make('current_value')
                    ->label('Valeur actuelle')
                    ->money('eur')
                    ->alignment(Alignment::End),

                TextColumn::make('current_percentage')
                    ->label('% actuel')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 1)
                    ->alignment(Alignment::End),

                TextColumn::make('target_percentage')
                    ->label('% cible')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 1)
                    ->alignment(Alignment::End),

                TextColumn::make('shares_to_buy')
                    ->label('Actions à acheter')
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->size(TextSize::Large)
                    ->alignment(Alignment::End),

                TextColumn::make('buy_cost')
                    ->label('Coût')
                    ->money('eur')
                    ->alignment(Alignment::End),

                TextColumn::make('new_percentage')
                    ->label('Nouveau %')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 1)
                    ->alignment(Alignment::End),
            ])
            ->paginated(false)
            ->emptyStateHeading('Aucun résultat')
            ->emptyStateDescription('Configurez vos allocations et cliquez sur "Calculer".');
    }

    public function calculate(): void
    {
        $data = $this->form->getState();
        $allocations = $data['allocations'] ?? [];
        $amount = (float) ($data['amount'] ?? 0);
        $wallet = ! empty($data['wallet_id']) ? Wallet::find((int) $data['wallet_id']) : null;

        if (empty($allocations)) {
            Notification::make()
                ->warning()
                ->title('Ajoutez au moins un titre')
                ->send();

            return;
        }

        $totalPercentage = array_sum(array_column($allocations, 'target_percentage'));
        if (abs($totalPercentage - 100) > 0.01) {
            Notification::make()
                ->danger()
                ->title('Le total des pourcentages doit être égal à 100%')
                ->body("Total actuel : {$totalPercentage}%")
                ->send();

            return;
        }

        $securities = $this->buildSecuritiesData($allocations, $wallet);

        $calculator = new RebalancingService;
        $results = $calculator->calculate($securities, $amount);

        $this->resultItems = [];
        foreach ($results['items'] as $index => $item) {
            $this->resultItems[$index] = $item;
        }
        $this->totalInvested = $results['total_invested'];
        $this->remainder = $results['remainder'];
        $this->hasResults = true;
    }

    public function saveProfile(): void
    {
        $data = $this->form->getState();
        $allocations = $data['allocations'] ?? [];

        if (empty($allocations)) {
            Notification::make()
                ->warning()
                ->title('Ajoutez au moins un titre avant de sauvegarder')
                ->send();

            return;
        }

        $walletId = ! empty($data['wallet_id']) ? (int) $data['wallet_id'] : null;
        $wallet = $walletId ? Wallet::find($walletId) : null;

        $profile = AllocationProfile::query()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'name' => $wallet ? $wallet->name : 'Global',
            ],
            [
                'wallet_id' => $walletId,
            ]
        );

        $profile->items()->delete();

        foreach ($allocations as $allocation) {
            $profile->items()->create([
                'security_id' => $allocation['security_id'],
                'target_percentage' => $allocation['target_percentage'],
            ]);
        }

        $this->dispatch('$refresh');

        Notification::make()
            ->success()
            ->title('Profil sauvegardé')
            ->send();
    }

    public function loadProfile(?string $profileId): void
    {
        if (! $profileId) {
            return;
        }

        $profile = AllocationProfile::query()
            ->with('items')
            ->where('user_id', auth()->id())
            ->find($profileId);

        if (! $profile) {
            return;
        }

        $allocations = $profile->items->map(fn ($item): array => [
            'security_id' => $item->security_id,
            'target_percentage' => $item->target_percentage,
        ])->all();

        $this->data['allocations'] = $allocations;
        $this->data['wallet_id'] = $profile->wallet_id ?? '';
        $this->resetResults();
    }

    public function resetResults(): void
    {
        $this->resultItems = [];
        $this->totalInvested = 0;
        $this->remainder = 0;
        $this->hasResults = false;
    }

    /**
     * @param  array<int, array{security_id: int|string, target_percentage: float|string}>  $allocations
     * @return array<int, array{security_id: int, name: string, price: float, quantity: float, target_percentage: float}>
     */
    private function buildSecuritiesData(array $allocations, ?Wallet $wallet): array
    {
        $securities = [];

        foreach ($allocations as $allocation) {
            $securityId = (int) $allocation['security_id'];
            $security = Security::query()->with('latestPrice')->find($securityId);

            if (! $security) {
                continue;
            }

            $price = $security->latestPrice?->close ?? 0;

            $quantityQuery = Transaction::query()
                ->withoutGlobalScope('user')
                ->where('user_id', auth()->id())
                ->where('security_id', $securityId);

            if ($wallet) {
                $quantityQuery->where('wallet_id', $wallet->id);
            }

            $quantity = (float) $quantityQuery->sum('quantity');

            $securities[] = [
                'security_id' => $securityId,
                'name' => $security->name,
                'price' => (float) $price,
                'quantity' => $quantity,
                'target_percentage' => (float) $allocation['target_percentage'],
            ];
        }

        return $securities;
    }
}
