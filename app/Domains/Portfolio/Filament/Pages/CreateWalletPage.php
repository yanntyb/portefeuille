<?php

namespace App\Domains\Portfolio\Filament\Pages;

use App\Domains\Portfolio\Models\Wallet;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class CreateWalletPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?string $navigationLabel = 'Ajouter un portefeuille';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'create-wallet';

    protected static ?string $title = 'Nouveau portefeuille';

    protected string $view = 'filament.pages.create-wallet-page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required(),
                ])
                    ->livewireSubmitHandler('create')
                    ->footer([
                        Actions::make([
                            Action::make('create')
                                ->label('Créer le portefeuille')
                                ->submit('create'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $wallet = Wallet::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
        ]);

        $this->redirect(WalletsConfigPage::getUrl());
    }
}
