<?php

/** Aides définies dans `tests/Pest.php` en Task 3. */
beforeEach(function () {
    $this->viteHotFile = hideViteHotFile();
});

afterEach(function () {
    restoreViteHotFile($this->viteHotFile);
});

it('enregistre le service worker et remplit son cache', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertScript("typeof navigator.serviceWorker !== 'undefined'", true)
        ->assertScript(
            <<<'JS'
            Promise.race([
                navigator.serviceWorker.ready.then(() => true),
                new Promise((resolve) => setTimeout(() => resolve(false), 10000)),
            ])
            JS,
            true,
        )
        ->assertScript(
            <<<'JS'
            new Promise((resolve) => {
                const deadline = Date.now() + 10000;
                const check = () => caches.keys().then((names) => {
                    if (names.some((name) => name.startsWith('argent-'))) { resolve(true); return; }
                    if (Date.now() > deadline) { resolve(false); return; }
                    setTimeout(check, 200);
                });
                check();
            })
            JS,
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('propose l\'installation quand le navigateur la signale', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    $page->script(<<<'JS'
        const event = new Event('beforeinstallprompt');
        event.prompt = () => Promise.resolve();
        event.userChoice = Promise.resolve({ outcome: 'accepted' });
        window.dispatchEvent(event);
    JS);

    $page
        ->assertScript("document.querySelector('[data-pwa-banner=install]') !== null", true)
        ->assertSee('Installer l\'app')
        ->assertNoJavaScriptErrors();
});
