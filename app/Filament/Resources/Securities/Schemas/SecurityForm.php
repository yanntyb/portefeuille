<?php

namespace App\Filament\Resources\Securities\Schemas;

use App\Models\Security;
use App\Services\YahooFinanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SecurityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('isin')
                    ->label('ISIN')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(12)
                    ->placeholder('FR0011871110')
                    ->live(onBlur: true),
                TextInput::make('name')
                    ->label('Nom')
                    ->maxLength(255)
                    ->placeholder('Nom du titre'),
                TextInput::make('ticker')
                    ->label('Ticker'),
            ]);
    }

    public static function updateFromIsinAction(): Action
    {
        return Action::make('updateFromIsin')
            ->label('Mise à jour')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->schema(self::searchSchema())
            ->mountUsing(function (Action $action, Schema $schema, EditRecord $livewire): void {
                $isin = $livewire->data['isin'] ?? null;

                if (empty($isin)) {
                    Notification::make()
                        ->title('Veuillez renseigner un ISIN')
                        ->warning()
                        ->send();

                    $action->cancel();

                    return;
                }

                self::mountSearchAction($action, $schema, $isin);
            })
            ->action(function (array $data, EditRecord $livewire): void {
                [$symbol, $name] = explode('|', $data['selected_result'], 2);

                $state = $livewire->data;
                $state['ticker'] = $symbol;
                $state['name'] = $name;
                $livewire->data = $state;
            });
    }

    public static function updateFromIsinTableAction(): Action
    {
        return Action::make('updateFromIsin')
            ->label('Mise à jour')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->iconButton()
            ->schema(self::searchSchema())
            ->mountUsing(function (Action $action, Schema $schema, Security $record): void {
                if (empty($record->isin)) {
                    Notification::make()
                        ->title('ISIN manquant')
                        ->warning()
                        ->send();

                    $action->cancel();

                    return;
                }

                self::mountSearchAction($action, $schema, $record->isin);
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

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function searchSchema(): array
    {
        return [
            Hidden::make('search_options'),
            Select::make('selected_result')
                ->label('Résultats')
                ->options(function (Get $get): array {
                    $json = $get('search_options');

                    return $json ? json_decode($json, true) : [];
                })
                ->required()
                ->searchable(),
        ];
    }

    private static function mountSearchAction(Action $action, Schema $schema, string $isin): void
    {
        $service = app(YahooFinanceService::class);
        $results = $service->searchTicker($isin);

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
    }
}
