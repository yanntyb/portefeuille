<?php

namespace App\Shared\Pwa;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

/**
 * Assemble le script servi à `/sw.js` : les constantes calculées côté PHP, puis le runtime
 * compilé par `vite.sw.config.ts`.
 */
class ServiceWorkerScript
{
    /**
     * Worker de repli en développement et tant que le second build n'a pas tourné : il se
     * retire et vide ses caches, plutôt que de servir des assets périmés pendant le HMR.
     */
    private const INERT = <<<'JS'
        self.addEventListener('install', () => self.skipWaiting());
        self.addEventListener('activate', (event) => {
            event.waitUntil((async () => {
                const names = await caches.keys();
                await Promise.all(names.map((name) => caches.delete(name)));
                await self.registration.unregister();
            })());
        });
        JS;

    public function render(): string
    {
        $version = Vite::manifestHash();
        $runtimePath = public_path('sw-runtime.js');

        if ($version === null || ! File::exists($runtimePath)) {
            return self::INERT;
        }

        $constants = implode("\n", [
            'self.CACHE_VERSION = '.json_encode($version, JSON_UNESCAPED_SLASHES).';',
            'self.PRECACHE_URLS = '.json_encode($this->precacheUrls(), JSON_UNESCAPED_SLASHES).';',
            'self.OFFLINE_URL = '.json_encode(route('pwa.offline', absolute: false), JSON_UNESCAPED_SLASHES).';',
        ]);

        return $constants."\n".File::get($runtimePath);
    }

    /**
     * Tout ce que le manifest Vite déclare, plus les deux documents dont le worker a besoin
     * pour répondre hors-ligne. Les pages sont code-splittées : sans précache, un chunk jamais
     * demandé manque au premier accès hors-ligne après un déploiement.
     *
     * @return list<string>
     */
    private function precacheUrls(): array
    {
        /** @var array<string, array{file: string, css?: list<string>}> $manifest */
        $manifest = json_decode(File::get(public_path('build/manifest.json')), true);

        $urls = ['/', route('pwa.offline', absolute: false)];

        foreach ($manifest as $entry) {
            $urls[] = '/build/'.$entry['file'];

            foreach ($entry['css'] ?? [] as $stylesheet) {
                $urls[] = '/build/'.$stylesheet;
            }
        }

        return array_values(array_unique($urls));
    }
}
