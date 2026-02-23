<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property-read Schema $form
 */
class EditAccount extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Mon Compte';

    protected static ?string $title = 'Mon Compte';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.edit-account';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique('users', 'email', ignorable: auth()->user()),
                    TextInput::make('password')
                        ->label('Nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->rule(Password::default())
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('passwordConfirmation')
                        ->label('Confirmer le mot de passe')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->requiredWith('password')
                        ->dehydrated(false),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Enregistrer')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        auth()->user()->update($data);

        Notification::make()
            ->success()
            ->title('Profil mis à jour')
            ->send();
    }
}
