<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TransactionForm
{
    private static function fillUnitPrice(Get $get, Set $set): void
    {
        $securityId = $get('security_id');
        $date = $get('date');

        if (! $securityId || ! $date) {
            return;
        }

        $price = SecurityPrice::query()
            ->where('security_id', $securityId)
            ->where('date', '<=', $date)
            ->orderByDesc('date')
            ->value('close');

        if ($price !== null) {
            $set('unit_price', $price);
        }
    }

    private static function syncWalletType(Set $set, ?string $walletId): void
    {
        if (! $walletId) {
            $set('wallet_type', null);

            return;
        }

        $name = Wallet::withoutGlobalScope('user')->where('id', $walletId)->value('name');
        $set('wallet_type', $name ? strtolower($name) : null);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('type')
                    ->label('Type')
                    ->options(TransactionType::class)
                    ->default(TransactionType::Buy)
                    ->required()
                    ->inline()
                    ->live(),

                Hidden::make('wallet_type')
                    ->dehydrated(false),

                Select::make('wallet_id')
                    ->label('Compte')
                    ->options(fn (): array => Wallet::query()->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => self::syncWalletType($set, $state))
                    ->afterStateHydrated(fn (Set $set, ?string $state) => self::syncWalletType($set, $state)),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillUnitPrice($get, $set)),

                Select::make('security_id')
                    ->label('Titre')
                    ->relationship('security', 'isin')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name
                        ? "{$record->isin} — {$record->name}"
                        : $record->isin)
                    ->searchable(['isin', 'name'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillUnitPrice($get, $set))
                    ->createOptionForm([
                        TextInput::make('isin')
                            ->label('ISIN')
                            ->required()
                            ->unique()
                            ->maxLength(12),
                        TextInput::make('name')
                            ->label('Nom')
                            ->maxLength(255),
                        TextInput::make('ticker')
                            ->label('Ticker'),
                    ])
                    ->hiddenJs(<<<'JS'
                        ! ['pea', 'cto'].includes($get('wallet_type'))
                        JS),

                TextInput::make('broker')
                    ->label('Courtier')
                    ->hiddenJs(<<<'JS'
                        $get('wallet_type') !== 'cto'
                        JS),

                TextInput::make('quantity')
                    ->label('Quantité')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->hiddenJs(<<<'JS'
                        ! ['pea', 'cto'].includes($get('wallet_type'))
                        JS)
                    ->rules([
                        fn (Get $get, ?Transaction $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                            $type = $get('type');

                            if ($type !== TransactionType::Sell->value && $type !== TransactionType::Sell) {
                                return;
                            }

                            $securityId = $get('security_id');
                            $walletId = $get('wallet_id');

                            if (! $securityId || ! $walletId) {
                                return;
                            }

                            $ownedQuantity = (float) Transaction::withoutGlobalScopes()
                                ->where('security_id', $securityId)
                                ->where('wallet_id', $walletId)
                                ->where('user_id', auth()->id())
                                ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                ->selectRaw("SUM(CASE WHEN type = 'buy' THEN quantity ELSE -quantity END) as total")
                                ->value('total');

                            if ((float) $value > $ownedQuantity) {
                                $fail("Vous ne pouvez pas vendre plus de {$ownedQuantity} actions (quantité possédée).");
                            }
                        },
                    ]),

                TextInput::make('unit_price')
                    ->label('Prix unitaire')
                    ->numeric()
                    ->prefix('€')
                    ->minValue(0)
                    ->hiddenJs(<<<'JS'
                        ! ['pea', 'cto'].includes($get('wallet_type'))
                        JS),

                TextInput::make('fees')
                    ->label('Frais')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->minValue(0)
                    ->hiddenJs(<<<'JS'
                        ! ['pea', 'cto'].includes($get('wallet_type'))
                        JS),

                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }
}
