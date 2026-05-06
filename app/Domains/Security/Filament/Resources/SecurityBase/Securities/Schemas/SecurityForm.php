<?php

namespace App\Filament\Resources\Securities\Schemas;

use App\Jobs\UpdateSecurityJob;
use App\Domains\Security\Models\Security;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

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
            ->label(fn (EditRecord $livewire) => $livewire->isUpdating ? 'Mise à jour en cours…' : 'Mettre à jour')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->disabled(fn (EditRecord $livewire) => $livewire->isUpdating)
            ->extraAttributes(fn (EditRecord $livewire) => $livewire->isUpdating ? ['class' => '[&_svg]:animate-spin'] : [])
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

                $ticker = $livewire->data['ticker'] ?? null;
                self::mountSearchAction($action, $schema, $isin, $ticker);
            })
            ->action(function (array $data, EditRecord $livewire): void {
                [$symbol, $name] = explode('|', $data['selected_result'], 2);

                /** @var Security $security */
                $security = $livewire->getRecord();

                $state = $livewire->data;
                $state['ticker'] = $symbol;
                $state['name'] = $name;
                $livewire->data = $state;

                $cacheKey = UpdateSecurityJob::cacheKeyFor($security->id);
                Cache::put($cacheKey, true, now()->addMinutes(10));
                UpdateSecurityJob::dispatch($security->id, $symbol, $name);

                $livewire->isUpdating = true;

                Notification::make()
                    ->title('Mise à jour lancée en arrière-plan')
                    ->success()
                    ->send();
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

                self::mountSearchAction($action, $schema, $record->isin, $record->ticker);
            })
            ->action(function (array $data, Security $record): void {
                [$symbol, $name] = explode('|', $data['selected_result'], 2);

                $cacheKey = UpdateSecurityJob::cacheKeyFor($record->id);
                Cache::put($cacheKey, true, now()->addMinutes(10));
                UpdateSecurityJob::dispatch($record->id, $symbol, $name);

                Notification::make()
                    ->title('Mise à jour lancée en arrière-plan')
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

    private static function mountSearchAction(Action $action, Schema $schema, string $isin, ?string $ticker = null): void
    {
        $service = app(\App\Services\YahooFinanceService::class);
        $results = $service->searchTicker($isin, $ticker);

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
