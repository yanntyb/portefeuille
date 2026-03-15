<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\AccountType;
use App\Enums\TransactionType;
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
                    ->isoDate('MMM YYYY')
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('Compte')
                    ->badge()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('security.isin')
                    ->label('ISIN')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
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
                TextColumn::make('realized_gain')
                    ->label('PV réalisée')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Compte')
                    ->options(AccountType::class),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(TransactionType::class),
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
