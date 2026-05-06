<?php

namespace App\Infrastructure\Extensions;

use App\Infrastructure\Contracts\Storeable;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class Store extends Extension
{
    /** @var array<string, array{state: array<string, mixed>, persist: bool}> */
    private array $stores = [];

    public function getId(): string
    {
        return 'store';
    }

    /**
     * @param  array<string, mixed>|Storeable  $initialState
     */
    public static function add(string $name, array|Storeable $initialState, bool $persist = false): void
    {
        $state = $initialState instanceof Storeable ? $initialState->toStore() : $initialState;
        app(static::class)->stores[$name] = ['state' => $state, 'persist' => $persist];
    }

    public function register(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_BEFORE,
            fn (): string => $this->buildScript(),
        );
    }

    private function buildScript(): string
    {
        $json = json_encode($this->stores, JSON_UNESCAPED_UNICODE);

        return <<<HTML
        <script>
            document.addEventListener('alpine:init', () => {
                const stores = {$json};

                for (const [name, { state, persist }] of Object.entries(stores)) {
                    let initial = state;

                    if (persist) {
                        try {
                            const saved = localStorage.getItem('store:' + name);
                            if (saved) initial = { ...state, ...JSON.parse(saved) };
                        } catch {}
                    }

                    Alpine.store(name, initial);

                    if (persist) {
                        Alpine.effect(() => {
                            try {
                                localStorage.setItem(
                                    'store:' + name,
                                    JSON.stringify(Alpine.store(name))
                                );
                            } catch {}
                        });
                    }
                }
            });
        </script>
        HTML;
    }
}
