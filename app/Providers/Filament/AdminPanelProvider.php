<?php

namespace App\Providers\Filament;

use App\Infrastructure\Filament\Pages\AssetPricePage;
use App\Infrastructure\Filament\Resources\AssetResource;
use App\Infrastructure\Filament\Widgets\AssetPriceChartWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->darkMode()
            ->resources([
                AssetResource::class,
            ])
            ->pages([
                AssetPricePage::class,
            ])
            ->widgets([
                AssetPriceChartWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                function (): string {
                    $logoutUrl = filament()->getLogoutUrl();
                    $loginUrl = filament()->getLoginUrl();

                    return Blade::render(
                        '<x-filament::icon-button
                            icon="heroicon-o-power"
                            color="gray"
                            label="Déconnexion"
                            x-on:click="
                                fetch(\''.$logoutUrl.'\', {
                                    method: \'POST\',
                                    headers: {
                                        \'X-CSRF-TOKEN\': document.querySelector(\'meta[name=csrf-token]\').content,
                                    },
                                }).then(() => window.location.href = \''.$loginUrl.'\')
                            "
                        />'
                    );
                },
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render(file_get_contents(resource_path('views/filament/pages/auth/demo-button.blade.php'))),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
