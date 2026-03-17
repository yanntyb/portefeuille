<?php

namespace App\Filament\Pages;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FrequencyUnit;
use App\Filament\Widgets\Securities\WalletFeesWidget;
use App\Filament\Widgets\Simulation\SimulationSectionWidget;
use App\Models\Wallet;
use App\Models\WalletFee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use UnitEnum;

class WalletPage extends AccountPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?string $slug = 'wallet';

    #[Url]
    public ?int $walletId = null;

    public float $versementMensuel = 500;

    public int $nbSimulations = 500;

    public function mount(): void
    {
        if ($this->walletId === null) {
            return;
        }

        $this->wallet = Wallet::findOrFail($this->walletId);
        parent::mount();
    }

    public function reloadSimulationAction(): Action
    {
        return Action::make('reloadSimulation')
            ->label('Relancer')
            ->icon('heroicon-m-arrow-path')
            ->iconButton()
            ->color('gray')
            ->action(function (): void {
                $this->dispatch('simulation-settings-updated',
                    versementMensuel: $this->versementMensuel,
                    tauxMoyen: $this->computeAnnualizedReturn(),
                    volatilite: $this->computePortfolioVolatility(),
                    nbSimulations: $this->nbSimulations,
                );
            });
    }

    public function infoProjectionMonteCarloAction(): Action
    {
        return Action::make('infoProjectionMonteCarlo')
            ->label('Informations')
            ->icon('heroicon-m-information-circle')
            ->iconButton()
            ->color('gray')
            ->modalHeading('Projection Monte Carlo')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->schema([
                Callout::make('Cette valeur est estimée à partir de l\'historique de vos titres.')
                    ->warning()
                    ->description('C\'est une hypothèse de départ : votre portefeuille pourrait être plus ou moins agité dans les années à venir. Vous pouvez ajuster cette valeur pour tester différents scénarios.'),
                Callout::make('Comment sont calculées les 3 courbes ?')
                    ->info()
                    ->description('Le simulateur rejoue 500 fois l\'évolution de votre portefeuille sur la durée choisie, avec des rendements aléatoires cohérents avec vos hypothèses. Les 3 courbes résument l\'ensemble des résultats : 10 % des simulations finissent sous la courbe pessimiste, 50 % sous la médiane, et 90 % sous la courbe optimiste.'),
            ]);
    }

    public function configureSimulationAction(): Action
    {
        return Action::make('configureSimulation')
            ->label('Paramètres')
            ->icon('heroicon-m-cog-6-tooth')
            ->iconButton()
            ->color('gray')
            ->modalHeading('Paramètres de la projection')
            ->fillForm(fn (): array => [
                'versementMensuel' => $this->versementMensuel,
                'tauxMoyen' => $this->computeAnnualizedReturn(),
                'volatilite' => $this->computePortfolioVolatility(),
                'nbSimulations' => $this->nbSimulations,
            ])
            ->schema([
                TextInput::make('versementMensuel')
                    ->label('Versement mensuel')
                    ->numeric()
                    ->suffix('€')
                    ->required()
                    ->minValue(0),
                TextInput::make('tauxMoyen')
                    ->label('Taux de rendement annuel moyen')
                    ->numeric()
                    ->suffix('%')
                    ->required()
                    ->minValue(0)
                    ->maxValue(50),
                TextInput::make('volatilite')
                    ->label('Volatilité annuelle')
                    ->numeric()
                    ->suffix('%')
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('nbSimulations')
                    ->label('Nombre de simulations')
                    ->numeric()
                    ->required()
                    ->minValue(100)
                    ->maxValue(5000)
                    ->helperText('Plus le nombre est élevé, plus le résultat est précis, mais le calcul est plus lent. Entre 200 et 1000 est un bon compromis.'),
            ])
            ->action(function (array $data): void {
                $this->versementMensuel = (float) $data['versementMensuel'];
                $this->nbSimulations = (int) $data['nbSimulations'];

                $this->dispatch('simulation-settings-updated',
                    versementMensuel: $this->versementMensuel,
                    tauxMoyen: (float) $data['tauxMoyen'],
                    volatilite: (float) $data['volatilite'],
                    nbSimulations: $this->nbSimulations,
                );
            });
    }

    public function configureFeesAction(): Action
    {
        return Action::make('configureFees')
            ->label('Configurer')
            ->icon('heroicon-m-cog-6-tooth')
            ->iconButton()
            ->color('gray')
            ->modalHeading('Frais du portefeuille')
            ->fillForm(fn (): array => [
                'fees' => $this->wallet->fees->map(fn (WalletFee $fee) => [
                    'name' => $fee->name,
                    'value' => $fee->value,
                    'unit' => $fee->unit->value,
                    'frequency' => $fee->frequency?->value,
                ])->toArray(),
            ])
            ->schema([
                Repeater::make('fees')
                    ->label(false)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->placeholder('Ex : Flat Tax, Frais Boursorama'),
                        TextInput::make('value')
                            ->label('Valeur')
                            ->numeric()
                            ->required()
                            ->placeholder('Ex : 30, 10'),
                        Select::make('unit')
                            ->label('Unité')
                            ->options(collect(CurrencyModificationUnit::cases())->mapWithKeys(
                                fn (CurrencyModificationUnit $unit) => [$unit->value => $unit->getLabel()]
                            ))
                            ->live()
                            ->required(),
                        Select::make('frequency')
                            ->label('Fréquence')
                            ->options(collect(FrequencyUnit::cases())->mapWithKeys(
                                fn (FrequencyUnit $freq) => [$freq->value => $freq->getLabel()]
                            ))
                            ->visible(fn (Get $get): bool => $get('unit') === CurrencyModificationUnit::Currency->value),
                    ])
                    ->columns(2)
                    ->addActionLabel('Ajouter un frais')
                    ->defaultItems(0),
            ])
            ->action(function (array $data): void {
                $this->wallet->fees()->delete();

                foreach ($data['fees'] as $feeData) {
                    $this->wallet->fees()->create([
                        'name' => $feeData['name'],
                        'value' => $feeData['value'],
                        'unit' => $feeData['unit'],
                        'frequency' => ($feeData['unit'] === CurrencyModificationUnit::Currency->value)
                            ? ($feeData['frequency'] ?? null)
                            : null,
                    ]);
                }

                Notification::make()
                    ->title('Frais enregistrés')
                    ->success()
                    ->send();
            });
    }

    protected function getExtraContentComponents(): array
    {
        return [
            Section::make('Simulation')
                ->collapsible()
                ->collapsed()
                ->persistCollapsed()
                ->id('wallet-simulation')
                ->afterHeader(fn () => [$this->configureSimulationAction()])
                ->schema([
                    Section::make('Projection Monte Carlo')
                        ->collapsible()
                        ->collapsed()
                        ->persistCollapsed()
                        ->id('wallet-simulation-montecarlo')
                        ->afterHeader(fn () => [$this->reloadSimulationAction(), $this->infoProjectionMonteCarloAction()])
                        ->schema([
                            Livewire::make(SimulationSectionWidget::class, [
                                'capitalInitial' => $this->getTotalValuation(),
                                'tauxMoyen' => $this->computeAnnualizedReturn(),
                                'volatilite' => $this->computePortfolioVolatility(),
                                'versementMensuel' => $this->versementMensuel,
                                'nbSimulations' => $this->nbSimulations,
                            ])->key('wallet-simulation-section'),
                        ]),
                ]),
            Section::make('Frais')
                ->collapsible()
                ->collapsed()
                ->persistCollapsed()
                ->id('wallet-fees')
                ->afterHeader(fn () => [$this->configureFeesAction()])
                ->schema([
                    Livewire::make(WalletFeesWidget::class, [
                        'tablePageClass' => static::class,
                        'shownSecurityIds' => $this->shownSecurityIds,
                        'walletId' => $this->wallet?->id,
                    ])->key('wallet-fees-widget'),
                ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString($this->wallet->name.' '.$this->getFormattedValuation());
    }

    public static function getNavigationItems(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return Wallet::query()
            ->withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->orderBy('id')
            ->get()
            ->map(fn (Wallet $wallet) => NavigationItem::make()
                ->label($wallet->name)
                ->url(static::getUrl(['walletId' => $wallet->id]))
                ->icon(self::iconForWallet($wallet))
                ->sort(self::sortForWallet($wallet))
                ->group('Portefeuille')
                ->isActiveWhen(fn () => request()->query('walletId') == $wallet->id)
            )->all();
    }

    private static function iconForWallet(Wallet $wallet): string|BackedEnum
    {
        return match ($wallet->name) {
            'PEA' => Heroicon::OutlinedChartBar,
            'CTO' => Heroicon::OutlinedBuildingLibrary,
            'Livret' => Heroicon::OutlinedBanknotes,
            default => Heroicon::OutlinedWallet,
        };
    }

    private static function sortForWallet(Wallet $wallet): int
    {
        return match ($wallet->name) {
            'PEA' => 2,
            'CTO' => 3,
            'Livret' => 4,
            default => 10,
        };
    }
}
