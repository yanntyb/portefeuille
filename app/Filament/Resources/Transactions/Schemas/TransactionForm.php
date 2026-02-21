<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\AccountType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TransactionForm
{
    private static function isSecurityAccount(Get $get): bool
    {
        return in_array($get('account_type'), [
            AccountType::Pea->value,
            AccountType::Cto->value,
        ]);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_type')
                    ->label('Type de compte')
                    ->options(AccountType::class)
                    ->required()
                    ->live(),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now()),

                Fieldset::make('Détails titre')
                    ->hiddenJs(<<<'JS'
                        ! ['pea', 'cto'].includes($get('account_type'))
                        JS)
                    ->schema([
                        Select::make('security_id')
                            ->label('Titre')
                            ->relationship('security', 'isin')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name
                                ? "{$record->isin} — {$record->name}"
                                : $record->isin)
                            ->searchable(['isin', 'name'])
                            ->preload()
                            ->columnSpanFull()
                            ->createOptionForm([
                                TextInput::make('isin')
                                    ->label('ISIN')
                                    ->required()
                                    ->unique()
                                    ->maxLength(12),
                                TextInput::make('name')
                                    ->label('Nom')
                                    ->maxLength(255),
                            ]),

                        TextInput::make('broker')
                            ->label('Courtier')
                            ->hiddenJs(<<<'JS'
                                $get('account_type') !== 'cto'
                                JS),

                        TextInput::make('quantity')
                            ->label('Quantité')
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('unit_price')
                            ->label('Prix unitaire')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0),

                        TextInput::make('fees')
                            ->label('Frais')
                            ->numeric()
                            ->prefix('€')
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),

                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }
}
