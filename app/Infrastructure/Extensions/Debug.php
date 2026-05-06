<?php

namespace App\Infrastructure\Extensions;

use App\Domains\User\Filament\Pages\DebugPage;
use Filament\Navigation\NavigationItem;
use Filament\Panel;

class Debug extends Extension
{
    public function getId(): string
    {
        return 'debug';
    }

    public function register(Panel $panel): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $panel->navigationItems([
            NavigationItem::make('Debug')
                ->url(fn (): string => DebugPage::getUrl())
                ->icon('heroicon-o-bug-ant')
                ->badge(fn (): ?string => session('debug.simulate_user') ? 'Utilisateur' : null)
                ->sort(999),
        ]);
    }
}
