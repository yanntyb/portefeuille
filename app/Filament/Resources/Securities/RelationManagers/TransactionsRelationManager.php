<?php

namespace App\Filament\Resources\Securities\RelationManagers;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    private static function syncWalletType(Set $set, ?string $walletId): void
    {
        if (! $walletId) {
            $set('wallet_type', null);

            return;
        }

        $name = Wallet::withoutGlobalScope('user')->where('id', $walletId)->value('name');
        $set('wallet_type', $name ? strtolower($name) : null);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('wallet_type')
                    ->dehydrated(false),

                Select::make('wallet_id')
                    ->label('Compte')
                    ->options(fn (): array => Wallet::query()->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => self::syncWalletType($set, $state))
                    ->afterStateHydrated(fn (Set $set, ?string $state) => self::syncWalletType($set, $state)),

                ToggleButtons::make('type')
                    ->label('Type')
                    ->options(TransactionType::class)
                    ->default(TransactionType::Buy)
                    ->required()
                    ->inline()
                    ->live(),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now()),

                TextInput::make('broker')
                    ->label('Courtier')
                    ->hiddenJs(<<<'JS'
                        $get('wallet_type') !== 'cto'
                        JS),

                TextInput::make('quantity')
                    ->label('Quantité')
                    ->numeric()
                    ->minValue(0)
                    ->rules([
                        fn (Get $get, ?Transaction $record, RelationManager $livewire): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record, $livewire): void {
                            $type = $get('type');

                            if ($type !== TransactionType::Sell->value && $type !== TransactionType::Sell) {
                                return;
                            }

                            $walletId = $get('wallet_id');

                            if (! $walletId) {
                                return;
                            }

                            $securityId = $livewire->getOwnerRecord()->id;

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
                    ->default(function (RelationManager $livewire): ?float {
                        $price = SecurityPrice::query()
                            ->where('security_id', $livewire->getOwnerRecord()->id)
                            ->orderByDesc('date')
                            ->value('close');

                        return $price !== null ? (float) $price : null;
                    }),

                TextInput::make('fees')
                    ->label('Frais')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->minValue(0),

                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return TransactionsTable::configure(
            $table->heading('')
        )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
