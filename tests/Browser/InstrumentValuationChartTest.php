<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentWithValuation(): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);

    foreach (['2026-01-15' => 90, '2026-03-15' => 110, '2026-07-01' => 100] as $date => $close) {
        Price::factory()->create(['asset_id' => $asset->id, 'date' => $date, 'close' => $close]);
    }

    foreach (['2026-01-15', '2026-03-15'] as $date) {
        Transaction::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'date' => $date,
            'quantity' => 5,
            'unit_price' => 80,
            'fees' => 0,
        ]);
    }

    return ['user' => $user, 'instrument' => $asset];
}

it('offers the periods alone, without a granularity switch', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] [data-chart-range] button')).map(el => el.textContent.trim()).join('|')",
            '1M|6M|1A|Max',
        )
        ->assertNoJavaScriptErrors();
});

it('plots the position value against what was invested, in euros', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "document.querySelector('[data-section=valuation] [aria-label]')?.getAttribute('aria-label')",
            'Valeur de la position comparée au montant investi.',
        )
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] text'))
                .filter(el => el.getAttribute('text-anchor') === 'end')
                .every(el => el.textContent.includes('€'))",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('scales the value axis to the visible values instead of anchoring it at zero', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    $page = visit("/instruments/{$asset->id}");
    $page->assertScript("document.querySelector('[data-section=valuation] [data-chart] svg') !== null", true);

    expect(lowestValueAxisLabel($page, 'valuation'))->toBeGreaterThan(0.0);

    $page->assertNoJavaScriptErrors();
});

it('draws the position value alone, the invested amount staying out of the way', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    $page = visit("/instruments/{$asset->id}");
    $page->assertScript("document.querySelector('[data-section=valuation] [data-chart] svg') !== null", true);

    expect(drawnLines($page, 'valuation'))->toBe('1|0');

    $page->click('[data-section=valuation] [data-series-toggle=invested]');

    expect(drawnLines($page, 'valuation'))->toBe('1|1');

    $page->assertNoJavaScriptErrors();
});

it('spells out the gain in the tooltip even while the invested line is hidden', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    $page = visit("/instruments/{$asset->id}");
    $page->assertScript("document.querySelector('[data-section=valuation] [data-chart] svg') !== null", true);

    $page->script("(() => {
        const chart = document.querySelector('[data-section=valuation] [data-chart]');
        const box = chart.getBoundingClientRect();
        chart.querySelector('svg').dispatchEvent(new MouseEvent('mousemove', {
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 2,
            bubbles: true,
            cancelable: true,
        }));
    })()");

    $tooltip = (string) $page->script(
        "document.querySelector('[data-section=valuation] [data-chart]').textContent",
    );

    expect($tooltip)->toContain('Valeur')
        ->and($tooltip)->toContain('Investi')
        ->and($tooltip)->toMatch('/Gain|Perte/');

    $page->assertNoJavaScriptErrors();
});

it('leaves the chart legend out, the lines speak for themselves', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithValuation();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=valuation] text'))
                .filter(el => ['Valeur', 'Investi'].includes(el.textContent.trim())).length",
            0,
        )
        ->assertNoJavaScriptErrors();
});
