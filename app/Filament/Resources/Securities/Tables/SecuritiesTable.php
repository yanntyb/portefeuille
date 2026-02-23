<?php

namespace App\Filament\Resources\Securities\Tables;

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
                    ->searchable(),
                TextColumn::make('total_quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('latestPrice.close')
                    ->label('Prix')
                    ->money('eur')
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
            ->headerActions([
                Action::make('create')
                    ->label('Nouveau titre')
                    ->form([
                        Select::make('security_id')
                            ->label('Titre')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Security::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('isin', 'like', "%{$search}%")
                                ->orWhere('ticker', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Security $s): array => [$s->id => "{$s->name} ({$s->isin})"])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Security::find($value)?->name)
                            ->createOptionForm([
                                TextInput::make('isin')
                                    ->label('ISIN')
                                    ->required()
                                    ->unique(Security::class)
                                    ->maxLength(12)
                                    ->placeholder('FR0011871110'),
                                TextInput::make('name')
                                    ->label('Nom')
                                    ->maxLength(255)
                                    ->placeholder('Nom du titre'),
                                TextInput::make('ticker')
                                    ->label('Ticker'),
                            ])
                            ->createOptionUsing(fn (array $data): int => Security::create($data)->getKey())
                            ->required(),
                    ])
                    ->action(fn () => Notification::make()
                        ->success()
                        ->title('Titre ajouté')
                        ->send()),
                Action::make('fetchAllPrices')
                    ->label('MAJ tous les prix')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (YahooFinanceService $service): void {
                        $securities = Security::whereHas('transactions')->get();
                        $totalInserted = 0;
                        $errors = 0;

                        foreach ($securities as $security) {
                            try {
                                $totalInserted += $service->fetchAndStorePrices($security);
                            } catch (TickerResolutionException) {
                                $errors++;
                            }
                        }

                        Notification::make()
                            ->title("{$totalInserted} prix mis à jour, {$errors} erreur(s)")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
