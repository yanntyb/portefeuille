<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Un an de cours quotidiens : la fenêtre de zoom n'a de sens que sur un historique dense.
 * Une migration héritée sème un utilisateur en dur ; on l'efface pour que le contrôleur
 * résolve bien celui du test.
 */
function userWithDenseEvolution(): User
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    foreach (range(0, 365) as $offset) {
        Price::factory()->create([
            'asset_id' => $asset->id,
            'date' => now()->subDays(365 - $offset)->format('Y-m-d'),
            'close' => 90 + sin($offset / 20) * 20,
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 80, 'date' => now()->subDays(365)->format('Y-m-d'),
    ]);

    return $user;
}

it('opens the evolution chart on the recent end of the history', function () {
    $this->actingAs(userWithDenseEvolution());

    visit('/')
        ->assertScript("document.querySelector('[data-section=evolution] [data-chart]')?.getAttribute('data-zoom-window')", '70-100')
        ->assertNoJavaScriptErrors();
});

it('keeps the zoom handles free of date labels', function () {
    $this->actingAs(userWithDenseEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const texts = document.querySelectorAll('[data-section=evolution] [data-chart] svg text');
                return [...texts].filter((text) => /\\d{4}-\\d{2}-\\d{2}/.test(text.textContent)).length;
            })()",
            0,
        )
        ->assertNoJavaScriptErrors();
});

it('narrows the visible window when the reader zooms in', function () {
    $this->actingAs(userWithDenseEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $before = $page->script("document.querySelector('[data-section=evolution] [data-chart]').getAttribute('data-zoom-window')");

    $page->script("(() => {
        const chart = document.querySelector('[data-section=evolution] [data-chart]');
        const box = chart.getBoundingClientRect();
        chart.querySelector('svg').dispatchEvent(new WheelEvent('wheel', {
            deltaY: 400,
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 3,
            bubbles: true,
            cancelable: true,
        }));
    })()");

    $after = $page->script("document.querySelector('[data-section=evolution] [data-chart]').getAttribute('data-zoom-window')");

    expect($after)->not->toBe($before);

    [$start, $end] = array_map('intval', explode('-', (string) $after));
    expect($end - $start)->toBeLessThan(30);

    $page->assertNoJavaScriptErrors();
});

it('zooms without asking the server for more history', function () {
    $this->actingAs(userWithDenseEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $page->script('(() => {
        window.__requestsAfterLoad = 0;
        const open = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (...args) {
            window.__requestsAfterLoad += 1;
            return open.apply(this, args);
        };
        const fetched = window.fetch;
        window.fetch = function (...args) {
            window.__requestsAfterLoad += 1;
            return fetched.apply(this, args);
        };
    })()');

    $page->script("(() => {
        const chart = document.querySelector('[data-section=evolution] [data-chart]');
        const box = chart.getBoundingClientRect();
        chart.querySelector('svg').dispatchEvent(new WheelEvent('wheel', {
            deltaY: -400,
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 3,
            bubbles: true,
            cancelable: true,
        }));
    })()");

    expect($page->script('window.__requestsAfterLoad'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});
