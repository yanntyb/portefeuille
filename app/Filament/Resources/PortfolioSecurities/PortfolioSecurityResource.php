<?php

namespace App\Filament\Resources\PortfolioSecurities;

use App\Filament\Resources\PortfolioSecurities\Pages\ListPortfolioSecurities;
use App\Models\Security;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PortfolioSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $slug = 'portefeuille';

    protected static ?string $modelLabel = 'titre';

    protected static ?string $pluralModelLabel = 'titres';

    protected static ?string $navigationLabel = 'Titres';

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
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
                    }),
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->forAuth());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortfolioSecurities::route('/'),
        ];
    }
}
