<?php

namespace App\Extensions;

use Filament\Actions\Action;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Vite;

class Style extends Extension
{
    public function getId(): string
    {
        return 'style';
    }

    public function boot(Panel $panel): void
    {
        FilamentAsset::register([
            Js::make('chartjs-plugins', Vite::asset('resources/js/chartjs-plugins.js'))->module(),
        ]);
    }

    public function register(Panel $panel): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(10));

        Section::configureUsing(fn (Section $section) => $section
            ->extraAttributes(['class' => 'fi-section-no-content-padding']));

        Action::configureUsing(function (Action $action): void {
            if ($action->getName() !== 'loadMore') {
                return;
            }

            $action->hidden(function ($livewire): bool {
                return method_exists($livewire, 'hasMoreRecords') && ! $livewire->hasMoreRecords();
            });
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn (): string => '<div id="topbar-page-title" class="flex items-center"></div>',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => <<<'HTML'
                <script>
                    (function () {
                        function setupSectionAutoCollapse() {
                            new MutationObserver(function (mutations) {
                                mutations.forEach(function (mutation) {
                                    const el = mutation.target;
                                    if (el.classList && el.classList.contains('fi-section') && el.classList.contains('fi-collapsed')) {
                                        el.querySelectorAll('.fi-section-content-ctn .fi-section').forEach(function (child) {
                                            const data = window.Alpine && Alpine.$data(child);
                                            if (data && data.isCollapsed === false) {
                                                data.isCollapsed = true;
                                            }
                                        });
                                    }
                                });
                            }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });
                        }

                        document.addEventListener('alpine:initialized', setupSectionAutoCollapse);
                    })();
                </script>
                <script>
                    (function () {
                        function teleportPageTitle() {
                            const target = document.getElementById('topbar-page-title');
                            const heading = document.querySelector('.fi-header-heading');
                            if (target) {
                                target.textContent = heading ? heading.textContent.trim() : '';
                            }
                        }

                        document.addEventListener('livewire:navigated', teleportPageTitle);
                        document.addEventListener('DOMContentLoaded', teleportPageTitle);
                    })();
                </script>
            HTML,
        );

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
                    .fi-topbar {
                        position: relative !important;
                    }
                    #topbar-page-title {
                        position: absolute;
                        left: 50%;
                        top: 50%;
                        transform: translate(-50%, -50%);
                        font-size: 1.125rem;
                        font-weight: 700;
                        color: white;
                    }
                    .fi-header-heading {
                        display: none !important;
                    }
                    .fi-user-menu {
                        display: none !important;
                    }
                    .fi-pagination-records-per-page-select {
                        display: none !important;
                    }
                    .fi-page-content {
                        row-gap: 0 !important;
                    }
                </style>
            HTML,
        );
    }
}
