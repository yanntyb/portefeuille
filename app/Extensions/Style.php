<?php

namespace App\Extensions;

use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;

class Style extends Extension
{
    public function getId(): string
    {
        return 'style';
    }

    public function register(Panel $panel): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(10));

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => <<<'HTML'
                <style>
                    .fi-ta-ctn {
                        border-radius: 0 !important;
                        box-shadow: none !important;
                        --tw-ring-shadow: 0 0 #0000 !important;
                        background: transparent !important;
                    }
                    .fi-topbar {
                        background-color: #000000 !important;
                        border: none !important;
                        box-shadow: none !important;
                    }
                    .fi-user-menu {
                        display: none !important;
                    }
                    .fi-pagination-records-per-page-select {
                        display: none !important;
                    }
                </style>
            HTML,
        );
    }
}
