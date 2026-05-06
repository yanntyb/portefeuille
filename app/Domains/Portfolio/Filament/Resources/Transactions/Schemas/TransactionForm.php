<?php

namespace App\Domains\Portfolio\Filament\Resources\Transactions\Schemas;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
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
use Illuminate\Database\Eloquent\Model;

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

    public static function configure(Schema $schema, ?int $walletId = null, ?int $securityId = null): Schema
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
                    ->afterStateHydrated(fn (Set $set, ?string $state) => self::syncWalletType($set, $state))
                    ->hidden($walletId !== null)
                    ->default($walletId),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillUnitPrice($get, $set)),

                Select::make('security_id')
                    ->label('Titre')
                    ->options(fn (): array => Security::query()
                        ->orderBy('isin')
                        ->get()
                        ->mapWithKeys(fn (Security $security): array => [
                            $security->id => $security->name
                                ? "{$security->isin} — {$security->name}"
                                : $security->isin,
                        ])
                        ->all())
                    ->searchable()
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
                    ->createOptionUsing(fn (array $data): int => Security::create($data)->id)
                    ->afterStateHydrated(function (Get $get, Set $set) use ($securityId): void {
                        if ($securityId !== null) {
                            self::fillUnitPrice($get, $set);
                        }
                    })
                    ->hidden($securityId !== null)
                    ->default($securityId)
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
                        fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
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
                                ->when($record instanceof Transaction, fn ($query) => $query->where('id', '!=', $record->id))
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
