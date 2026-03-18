<?php

namespace App\Providers\Filament;

use App\Extensions\Debug;
use App\Extensions\Pwa;
use App\Extensions\Store;
use App\Extensions\Style;
use App\Extensions\TablePersistence;
use App\Extensions\Transition;
use App\Filament\Pages\Auth\Login;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
            ->spa(hasPrefetching: true)
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make('Portefeuille'),
                NavigationGroup::make('Outils'),
                NavigationGroup::make('Administration'),
            ])
            ->navigationItems([
                NavigationItem::make('Simulation')
                    ->url('#')
                    ->icon('heroicon-o-calculator')
                    ->group('Outils')
                    ->sort(1),
                NavigationItem::make('Logs')
                    ->url('/log-viewer', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-document-text')
                    ->group('Administration')
                    ->sort(100)
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->brandName('')
            ->darkMode(isForced: true)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->hidden(),
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('
                    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                        @csrf
                        <x-filament::icon-button
                            type="submit"
                            icon="heroicon-o-power"
                            color="gray"
                            label="Déconnexion"
                        />
                    </form>
                '),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render(file_get_contents(resource_path('views/filament/pages/auth/demo-button.blade.php'))),
            )
            ->plugins([
                Pwa::make(),
                Style::make(),
                Debug::make(),
                Transition::make(),
                Store::make(),
                TablePersistence::make(),
            ])
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
