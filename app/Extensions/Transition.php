<?php

namespace App\Extensions;

use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class Transition extends Extension
{
    public function getId(): string
    {
        return 'transition';
    }

    public function register(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => <<<'HTML'
                <style>
                    /*
                     * View Transitions API — transitions de page en mode SPA Livewire.
                     *
                     * Éléments persistants entre pages (morphing DOM) :
                     * le sidebar et la topbar restent en place pendant que le contenu
                     * principal effectue un fondu entrant/sortant.
                     */

                    /* Éléments persistants : ils morphent entre les états */
                    .fi-sidebar {
                        view-transition-name: fi-sidebar;
                    }

                    .fi-topbar {
                        view-transition-name: fi-topbar;
                    }

                    /* Zone de contenu principal : fondu opacité */
                    .fi-main {
                        view-transition-name: fi-main;
                    }

                    /* Sortie : fondu vers transparent */
                    ::view-transition-old(fi-main) {
                        animation: 180ms ease-out both vt-fade-out;
                    }

                    /* Entrée : fondu depuis transparent, légèrement décalé */
                    ::view-transition-new(fi-main) {
                        animation: 220ms ease-in 80ms both vt-fade-in;
                    }

                    /* Les éléments persistants ne s'animent pas — ils morphent */
                    ::view-transition-old(fi-sidebar),
                    ::view-transition-new(fi-sidebar),
                    ::view-transition-old(fi-topbar),
                    ::view-transition-new(fi-topbar) {
                        animation: none;
                    }

                    @keyframes vt-fade-out {
                        from { opacity: 1; }
                        to   { opacity: 0; }
                    }

                    @keyframes vt-fade-in {
                        from { opacity: 0; }
                        to   { opacity: 1; }
                    }

                    /* Accessibilité : désactive les animations si préféré */
                    @media (prefers-reduced-motion: reduce) {
                        ::view-transition-old(*),
                        ::view-transition-new(*) {
                            animation: none !important;
                        }
                    }
                </style>
            HTML,
        );
    }
}
