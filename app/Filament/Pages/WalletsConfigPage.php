<?php

namespace App\Filament\Pages;

use App\Models\Wallet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class WalletsConfigPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?string $navigationLabel = 'Configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'wallets';

    protected static ?string $title = 'Configuration des portefeuilles';

    protected string $view = 'filament.pages.wallets-config-page';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Nouveau portefeuille')
                ->url(CreateWalletPage::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Wallet::query())
            ->recordUrl(fn (Wallet $record) => WalletPage::getUrl(['walletId' => $record->id]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom'),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required(),
                    ]),
                DeleteAction::make(),
            ])
            ->paginated(false);
    }
}
