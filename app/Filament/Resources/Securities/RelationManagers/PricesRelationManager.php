<?php

namespace App\Filament\Resources\Securities\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Historique des prix';

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('open')
                    ->label('Ouverture')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('high')
                    ->label('Plus haut')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('low')
                    ->label('Plus bas')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('close')
                    ->label('Clôture')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('volume')
                    ->label('Volume')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
