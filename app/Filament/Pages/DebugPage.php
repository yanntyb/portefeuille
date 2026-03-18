<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;

class DebugPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Debug';

    protected static ?string $slug = 'debug';

    protected string $view = 'filament.pages.debug-page';

    public bool $simulateUser = false;

    public function mount(): void
    {
        $this->simulateUser = session('debug.simulate_user', false);
    }

    public function toggleRoleAction(): Action
    {
        return Action::make('toggleRole')
            ->label($this->simulateUser ? 'Repasser en Admin' : 'Simuler Utilisateur')
            ->icon($this->simulateUser ? 'heroicon-o-shield-check' : 'heroicon-o-user')
            ->color($this->simulateUser ? 'success' : 'warning')
            ->action(function (): void {
                $this->simulateUser = ! $this->simulateUser;
                session(['debug.simulate_user' => $this->simulateUser]);

                $this->redirect(static::getUrl());
            });
    }
}
