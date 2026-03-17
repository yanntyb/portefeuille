<?php

namespace App\Filament\Pages;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FrequencyUnit;
use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class WalletsConfigPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?string $navigationLabel = 'Configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'wallets';

    protected static ?string $title = 'Configuration des portefeuilles';

    protected string $view = 'filament.pages.wallets-config-page';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Nouveau portefeuille')
                ->url(CreateWalletPage::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Wallet::query())
            ->recordUrl(fn (Wallet $record) => WalletPage::getUrl(['walletId' => $record->id]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom'),
            ])
            ->recordActions([
                Action::make('fees')
                    ->label('Frais')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->modalHeading(fn (Wallet $record) => "Frais — {$record->name}")
                    ->fillForm(fn (Wallet $record) => [
                        'fees' => $record->fees->map(fn (WalletFee $fee) => [
                            'name' => $fee->name,
                            'value' => $fee->value,
                            'unit' => $fee->unit->value,
                            'frequency' => $fee->frequency?->value,
                        ])->toArray(),
                    ])
                    ->schema([
                        Repeater::make('fees')
                            ->label('Frais du portefeuille')
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
                    ->action(function (Wallet $record, array $data): void {
                        $record->fees()->delete();

                        foreach ($data['fees'] as $feeData) {
                            $record->fees()->create([
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
                    }),
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required(),
                    ]),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }
}
