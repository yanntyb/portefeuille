<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Seeds a portfolio whose price history is far longer than the six-month window the
 * dashboard opens on, so the chart both overflows its container and has more to load.
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function userWithLongEvolution(): User
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 80,
        'date' => '2024-01-01',
    ]);
    $rows = [];
    $day = Carbon::parse('2024-01-01');
    $end = Carbon::parse('2026-01-01');
    $close = 80.0;

    while ($day->lessThanOrEqualTo($end)) {
        $close += 0.05;
        $rows[] = [
            'asset_id' => $asset->id,
            'date' => $day->format('Y-m-d H:i:s'),
            'open' => $close,
            'high' => $close,
            'low' => $close,
            'close' => $close,
            'volume' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $day->addDay();
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        Price::query()->insert($chunk);
    }

    return $user;
}

it('drops the period picker from the evolution section', function () {
    $this->actingAs(userWithLongEvolution());

    visit('/')
        ->assertScript("document.querySelectorAll('[data-section=evolution] button').length", 0)
        ->assertNoJavaScriptErrors();
});

it('overflows the evolution chart into a horizontal scroller', function () {
    $this->actingAs(userWithLongEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const scroller = document.querySelector('[data-evolution-scroller]');
                return scroller.scrollWidth > scroller.clientWidth
                    && getComputedStyle(scroller).overflowX === 'auto';
            })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('opens the evolution chart on the most recent point', function () {
    $this->actingAs(userWithLongEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const scroller = document.querySelector('[data-evolution-scroller]');
                return scroller.scrollWidth - scroller.clientWidth - scroller.scrollLeft < 2;
            })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('keeps the value axis outside the scroller so it stays visible', function () {
    $this->actingAs(userWithLongEvolution());

    $page = visit('/')->assertScript("document.querySelector('[data-evolution-axis]') !== null", true);

    $before = $page->script("document.querySelector('[data-evolution-axis]').getBoundingClientRect().left");

    $page->script("document.querySelector('[data-evolution-scroller]').scrollLeft = 0");
    $page->wait(1);

    expect($page->script("document.querySelector('[data-evolution-axis]').getBoundingClientRect().left"))
        ->toBe($before);
});

it('loads older history when the evolution chart is scrolled to its left edge', function () {
    $this->actingAs(userWithLongEvolution());

    $page = visit('/')->assertScript("document.querySelector('[data-evolution-scroller]') !== null", true);

    $before = $page->script("document.querySelector('[data-evolution-scroller]').scrollWidth");

    $page->script("document.querySelector('[data-evolution-scroller]').scrollLeft = 0");
    $page->wait(3);

    expect($page->script("document.querySelector('[data-evolution-scroller]').scrollWidth"))
        ->toBeGreaterThan($before);
});

it('aligns the fixed value axis with the plot area of the scrolling chart', function () {
    $this->actingAs(userWithLongEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const rect = (root) => {
                    const grid = root.querySelector('.apexcharts-grid');
                    const box = grid.getBoundingClientRect();
                    return [Math.round(box.top), Math.round(box.height)].join(':');
                };
                return rect(document.querySelector('[data-evolution-axis]'))
                    === rect(document.querySelector('[data-evolution-scroller]'));
            })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});
