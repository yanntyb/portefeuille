<?php

namespace App\Filament\Resources\Securities\Tables;

use App\Models\Security;
use App\Models\SecurityPrice;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SecuritiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->icon(fn (Security $record, $livewire) => in_array($record->id, $livewire->pricelessSecurityIds) ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger')
                    ->tooltip(fn (Security $record, $livewire) => in_array($record->id, $livewire->pricelessSecurityIds) ? 'Les prix de ce titre n\'ont pas pu être récupérés automatiquement' : null),
                TextColumn::make('valuation')
                    ->label('Valorisation')
                    ->state(function (Security $record): ?float {
                        $val = $record->currentValuation();

                        return $val > 0.0 ? $val : null;
                    })
                    ->money('EUR')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->leftJoinSub(
                            SecurityPrice::query()
                                ->select('security_id', 'close')
                                ->whereIn('id', function ($sub) {
                                    $sub->selectRaw('MAX(id)')
                                        ->from('security_prices')
                                        ->groupBy('security_id');
                                }),
                            'lp',
                            'securities.id',
                            'lp.security_id',
                        )
                        ->orderByRaw('(COALESCE(total_quantity, 0) * COALESCE(MAX(lp.close), 0)) '.$direction)
                    ),
                TextColumn::make('performance')
                    ->label('Performance')
                    ->state(function (Security $record): ?float {
                        $close = $record->latestPrice?->close;
                        $pru = $record->pru;

                        if ($close === null || $pru === null || (float) $pru === 0.0) {
                            return null;
                        }

                        return ((float) $close - (float) $pru) / (float) $pru * 100;
                    })
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' %')
                    ->color(fn ($state) => match (true) {
                        $state === null => null,
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => null,
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->leftJoinSub(
                            SecurityPrice::query()
                                ->select('security_id', 'close')
                                ->whereIn('id', function ($sub) {
                                    $sub->selectRaw('MAX(id)')
                                        ->from('security_prices')
                                        ->groupBy('security_id');
                                }),
                            'lp_perf',
                            'securities.id',
                            'lp_perf.security_id',
                        )
                        ->orderByRaw('CASE WHEN pru > 0 THEN (COALESCE(MAX(lp_perf.close), 0) - pru) / pru ELSE 0 END '.$direction)
                    ),
                TextColumn::make('total_quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('latestPrice.close')
                    ->label('Prix actuel')
                    ->money('EUR'),
                TextColumn::make('pru')
                    ->label('PRU')
                    ->money('EUR'),
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable(),
            ])
            ->emptyStateHeading('Aucun titre')
            ->defaultSort('valuation', 'desc')
            ->recordActions([
                Action::make('toggleVisibility')
                    ->label('')
                    ->icon(fn (Security $record, $livewire) => in_array($record->id, $livewire->shownSecurityIds) ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->iconButton()
                    ->color(fn (Security $record, $livewire) => in_array($record->id, $livewire->shownSecurityIds) ? 'primary' : 'gray')
                    ->action(fn (Security $record, $livewire) => $livewire->toggleSecurity($record->id)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->headerActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('showSelected')
                        ->label('Afficher')
                        ->icon('heroicon-o-eye')
                        ->action(function (Collection $records, $livewire): void {
                            foreach ($records as $record) {
                                if (! in_array($record->id, $livewire->shownSecurityIds)) {
                                    $livewire->shownSecurityIds[] = $record->id;
                                }
                            }
                            $livewire->dispatch('security-visibility-changed', shownSecurityIds: $livewire->shownSecurityIds);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hideSelected')
                        ->label('Masquer')
                        ->icon('heroicon-o-eye-slash')
                        ->action(function (Collection $records, $livewire): void {
                            $livewire->shownSecurityIds = array_values(array_diff(
                                $livewire->shownSecurityIds,
                                $records->pluck('id')->all()
                            ));
                            $livewire->dispatch('security-visibility-changed', shownSecurityIds: $livewire->shownSecurityIds);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
