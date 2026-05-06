<?php

namespace App\Domains\Portfolio\Filament\Pages;

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Models\WalletFee;
use App\Filament\Schemas\WalletFeesSchema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
                            'scope' => $fee->scope?->value,
                            'frequency' => $fee->frequency?->value,
                        ])->toArray(),
                    ])
                    ->schema([
                        WalletFeesSchema::make('Frais du portefeuille'),
                    ])
                    ->action(function (Wallet $record, array $data): void {
                        $record->fees()->delete();

                        foreach ($data['fees'] as $feeData) {
                            $record->fees()->create([
                                'name' => $feeData['name'],
                                'value' => $feeData['value'],
                                'unit' => $feeData['unit'],
                                'scope' => ($feeData['unit'] === CurrencyModificationUnit::Percentage->value)
                                    ? ($feeData['scope'] ?? null)
                                    : null,
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
