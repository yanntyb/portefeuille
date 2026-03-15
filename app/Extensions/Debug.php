<?php

namespace App\Extensions;

use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

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

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_END,
            fn (): string => Blade::render('<livewire:debug-button />'),
        );
    }
}
