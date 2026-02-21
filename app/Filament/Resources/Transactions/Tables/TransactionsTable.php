<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\AccountType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M Y')
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('Compte')
                    ->badge()
                    ->sortable(),
                TextColumn::make('security.isin')
                    ->label('ISIN')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('broker')
                    ->label('Courtier')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 4)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label('Prix unitaire')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('fees')
                    ->label('Frais')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Compte')
                    ->options(AccountType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
