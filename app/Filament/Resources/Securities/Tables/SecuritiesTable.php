<?php

namespace App\Filament\Resources\Securities\Tables;

use App\Models\Security;
use App\Models\SecurityPrice;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->tooltip(fn (Security $record, $livewire) => in_array($record->id, $livewire->pricelessSecurityIds) ? 'Ce titre est masqué car les prix n\'ont pas pu être récupérés automatiquement' : null),
                TextColumn::make('valuation')
                    ->label('Valorisation')
                    ->state(function (Security $record): ?float {
                        $close = $record->latestPrice?->close;

                        if ($close === null || $record->total_quantity === null) {
                            return null;
                        }

                        return (float) $record->total_quantity * (float) $close;
                    })
                    ->money('eur')
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
                    ->label('Performances')
                    ->state(function (Security $record): ?float {
                        $close = $record->latestPrice?->close;

                        if ($close === null || $record->total_quantity === null || ! $record->total_invested) {
                            return null;
                        }

                        $valuation = (float) $record->total_quantity * (float) $close;
                        $totalInvested = (float) $record->total_invested;

                        return ($valuation - $totalInvested) / $totalInvested * 100;
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
                        ->orderByRaw('CASE WHEN total_invested > 0 THEN (COALESCE(total_quantity, 0) * COALESCE(MAX(lp_perf.close), 0) - total_invested) / total_invested ELSE 0 END '.$direction)
                    ),
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable(),
            ])
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
            ->headerActions([]);
    }
}
