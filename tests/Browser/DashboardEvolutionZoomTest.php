<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Trois ans de cours quotidiens par défaut : le plancher d'un an n'est observable que sur un
 * historique plus long que lui. Une migration héritée sème un utilisateur en dur ; on l'efface
 * pour que le contrôleur résolve bien celui du test.
 */
function userWithDenseEvolution(int $days = 1095): User
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    foreach (range(0, $days) as $offset) {
        Price::factory()->create([
            'asset_id' => $asset->id,
            'date' => now()->subDays($days - $offset)->format('Y-m-d'),
            'close' => 90 + sin($offset / 20) * 20,
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 80, 'date' => now()->subDays($days)->format('Y-m-d'),
    ]);

    return $user;
}

it('opens the evolution chart on the last twelve months', function () {
    $this->actingAs(userWithDenseEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $window = (string) $page->script("document.querySelector('[data-section=evolution] [data-chart]').getAttribute('data-zoom-window')");
    [$start, $end] = array_map('floatval', explode('-', $window));

    expect($end)->toBe(100.0);
    expect($end - $start)->toBeGreaterThan(32.0)->toBeLessThan(35.0);

    $page->assertNoJavaScriptErrors();
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

it('never shows less than a year when the reader zooms in', function () {
    $this->actingAs(userWithDenseEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    foreach (range(1, 3) as $ignored) {
        scrollChart($page, 'evolution', 400);
    }

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThan(32.0);

    $page->assertNoJavaScriptErrors();
});

it('widens the visible window when the reader zooms out', function () {
    $this->actingAs(userWithDenseEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $before = zoomWindowSpan($page, 'evolution');
    scrollChart($page, 'evolution', -400);

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThan($before);

    $page->assertNoJavaScriptErrors();
});

it('shows the whole history when it is shorter than a year', function () {
    $this->actingAs(userWithDenseEvolution(120));

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    expect(zoomWindowSpan($page, 'evolution'))->toBe(100.0);

    scrollChart($page, 'evolution', 400);

    expect(zoomWindowSpan($page, 'evolution'))->toBe(100.0);

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

    scrollChart($page, 'evolution', -400);

    expect($page->script('window.__requestsAfterLoad'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});
