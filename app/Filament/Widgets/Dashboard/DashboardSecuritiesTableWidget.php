<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Support\MarketCalendar;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DashboardSecuritiesTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    public function mount(): void
    {
        $this->computeSecurityVisibility();
    }

    private function computeSecurityVisibility(): void
    {
        $allIds = Security::query()
            ->whereHas('transactions', fn ($q) => $q->where('user_id', auth()->id()))
            ->pluck('id')
            ->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->pluck('security_id')
            ->unique()
            ->all();

        $this->shownSecurityIds = $idsWithPrice;
        $this->pricelessSecurityIds = array_values(array_diff($allIds, $idsWithPrice));
    }

    public function toggleSecurity(int $id): void
    {
        if (in_array($id, $this->shownSecurityIds)) {
            $this->shownSecurityIds = array_values(array_diff($this->shownSecurityIds, [$id]));
        } else {
            $this->shownSecurityIds[] = $id;
        }

        $this->dispatch('security-visibility-changed', shownSecurityIds: $this->shownSecurityIds);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn (): Builder => Security::query()->forAuth())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->icon(fn (Security $record) => in_array($record->id, $this->pricelessSecurityIds) ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger')
                    ->tooltip(fn (Security $record) => in_array($record->id, $this->pricelessSecurityIds) ? 'Ce titre est masqué car les prix n\'ont pas pu être récupérés automatiquement' : null),
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
                    ->icon(fn (Security $record) => in_array($record->id, $this->shownSecurityIds) ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->iconButton()
                    ->color(fn (Security $record) => in_array($record->id, $this->shownSecurityIds) ? 'primary' : 'gray')
                    ->action(fn (Security $record) => $this->toggleSecurity($record->id)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->headerActions([]);
    }
}
