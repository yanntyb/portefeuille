<?php

namespace App\Filament\Resources\Securities\Tables;

use App\Models\Security;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class SecuritiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->icon(fn (Security $record, $livewire) => in_array($record->id, $livewire->pricelessSecurityIds) ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger')
                    ->tooltip(fn (Security $record, $livewire) => in_array($record->id, $livewire->pricelessSecurityIds) ? 'Ce titre est masqué car les prix n\'ont pas pu être récupérés automatiquement' : null),
                TextColumn::make('total_quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('latestPrice.close')
                    ->label('Prix')
                    ->money('eur')
                    ->description(fn (Security $record): ?string => $record->latestPrice?->date?->translatedFormat('d M Y'))
                    ->sortable(),
                TextColumn::make('valuation')
                    ->label('Valorisation')
                    ->state(function (Security $record): ?float {
                        $close = $record->latestPrice?->close;

                        if ($close === null || $record->total_quantity === null) {
                            return null;
                        }

                        return (float) $record->total_quantity * (float) $close;
                    })
                    ->money('eur'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('toggleVisibility')
                    ->label('')
                    ->icon(fn (Security $record, $livewire) => in_array($record->id, $livewire->shownSecurityIds) ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->iconButton()
                    ->color(fn (Security $record, $livewire) => in_array($record->id, $livewire->shownSecurityIds) ? 'primary' : 'gray')
                    ->action(fn (Security $record, $livewire) => $livewire->toggleSecurity($record->id)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->headerActions([]);
    }
}
