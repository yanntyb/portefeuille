<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Security;
use App\Models\SecurityPrice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DashboardSecuritiesTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn (): Builder => Security::query()->forAuth())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
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
                        ->orderByRaw('(COALESCE(total_quantity, 0) * COALESCE(lp.close, 0)) '.$direction)
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
                        ->orderByRaw('CASE WHEN total_invested > 0 THEN (COALESCE(total_quantity, 0) * COALESCE(lp_perf.close, 0) - total_invested) / total_invested ELSE 0 END '.$direction)
                    ),
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable(),
            ])
            ->defaultSort('valuation', 'desc')
            ->headerActions([])
            ->recordActions([]);
    }
}
