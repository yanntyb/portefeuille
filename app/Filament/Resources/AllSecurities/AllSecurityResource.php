<?php

namespace App\Filament\Resources\AllSecurities;

use App\Filament\Resources\AllSecurities\Pages\ListAllSecurities;
use App\Models\Security;
use App\Services\YahooFinanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'securities';

    protected static ?string $modelLabel = 'titre';

    protected static ?string $pluralModelLabel = 'titres';

    protected static ?string $navigationLabel = 'Tous les titres';

    protected static ?string $breadcrumb = 'Titres';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('isin')
                    ->label('ISIN')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ticker')
                    ->label('Ticker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->sortable(),
                TextColumn::make('latestPrice.close')
                    ->label('Dernier prix')
                    ->money('eur')
                    ->sortable(),
            ])
            ->recordActions([
                self::updateFromIsinAction(),
            ]);
    }

    public static function updateFromIsinAction(): Action
    {
        return Action::make('updateFromIsin')
            ->label('Mise à jour')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->iconButton()
            ->schema([
                Hidden::make('search_options'),
                Select::make('selected_result')
                    ->label('Résultats')
                    ->options(function (Get $get): array {
                        $json = $get('search_options');

                        return $json ? json_decode($json, true) : [];
                    })
                    ->required()
                    ->searchable(),
            ])
            ->mountUsing(function (Action $action, Schema $schema, Security $record): void {
                if (empty($record->isin)) {
                    Notification::make()
                        ->title('ISIN manquant')
                        ->warning()
                        ->send();

                    $action->cancel();

                    return;
                }

                $service = app(YahooFinanceService::class);
                $results = $service->searchTicker($record->isin);

                if ($results === []) {
                    Notification::make()
                        ->title('Aucun résultat trouvé')
                        ->warning()
                        ->send();

                    $action->cancel();

                    return;
                }

                $options = [];
                foreach ($results as $result) {
                    $value = $result['symbol'].'|'.$result['name'];
                    $label = "{$result['symbol']} — {$result['name']} ({$result['exchange']})";
                    $options[$value] = $label;
                }

                $schema->fill(['search_options' => json_encode($options)]);
            })
            ->action(function (array $data, Security $record): void {
                [$symbol, $name] = explode('|', $data['selected_result'], 2);

                $record->update([
                    'ticker' => $symbol,
                    'name' => $name,
                ]);

                Notification::make()
                    ->title('Titre mis à jour')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllSecurities::route('/'),
        ];
    }
}
