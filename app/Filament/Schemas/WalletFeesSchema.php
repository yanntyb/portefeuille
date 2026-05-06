<?php

namespace App\Filament\Schemas;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FeeScope;
use App\Enums\FrequencyUnit;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class WalletFeesSchema
{
    public static function make(string|false $label = false): Repeater
    {
        return Repeater::make('fees')
            ->label($label)
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
                Select::make('scope')
                    ->label('Appliqué sur')
                    ->options(FeeScope::class)
                    ->visible(fn (Get $get): bool => $get('unit') === CurrencyModificationUnit::Percentage->value)
                    ->required(fn (Get $get): bool => $get('unit') === CurrencyModificationUnit::Percentage->value),
                Select::make('frequency')
                    ->label('Fréquence')
                    ->options(collect(FrequencyUnit::cases())->mapWithKeys(
                        fn (FrequencyUnit $freq) => [$freq->value => $freq->getLabel()]
                    ))
                    ->visible(fn (Get $get): bool => $get('unit') === CurrencyModificationUnit::Currency->value),
            ])
            ->columns(2)
            ->addActionLabel('Ajouter un frais')
            ->defaultItems(0);
    }
}
