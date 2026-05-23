<?php

namespace App\Infrastructure\Filament\Resources;

use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\Asset\Models\Enums\AssetType;
use App\Infrastructure\Filament\Resources\Pages\ListAssets;
use App\Infrastructure\Filament\Resources\Pages\ViewAsset;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationLabel = 'Actifs';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (AssetType $state): string => $state->getColor())
                    ->formatStateUsing(fn (AssetType $state): string => $state->getLabel()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'view' => ViewAsset::route('/{record}'),
        ];
    }
}
