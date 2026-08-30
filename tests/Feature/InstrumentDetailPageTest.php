<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('renders a held instrument sheet with its position and transactions', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 80]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);

    $this->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->where('instrument.name', 'ACME')
            ->where('instrument.position.marketValue', fn ($v) => (float) $v === 1000.0)
            ->has('instrument.transactions', 1)
            ->has('performances', 5)
            ->where('performances.0.key', 'YTD')
            ->where('performances.0.startDate', '2026-01-01')
            ->where('performances.4.key', 'MAX')
            ->missing('priceHistory')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('priceHistory.labels', 2)
            )
        );
});

it('hides the position when the instrument is not held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->where('instrument.position', null)
        );
});

it('returns 404 for an unknown instrument', function () {
    User::factory()->create();

    $this->get('/asset/999')->assertNotFound();
});

it('defers the per-title valuation series and loads it on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->missing('valuation')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels', 1)
                ->has('valuation.valuations', 1)
                ->has('valuation.invested', 1)
                ->has('valuation.prices', 1)
            )
        );
});

it('sends the whole valuation history, sampled week by week', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);

    foreach (range(0, 400) as $offset) {
        Price::factory()->create([
            'asset_id' => $asset->id,
            'date' => Carbon::parse('2026-01-01')->addDays($offset)->format('Y-m-d'),
            'close' => 100,
        ]);
    }

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('valuation.labels', function (Collection $labels): bool {
                    $dates = $labels->map(fn (string $label): Carbon => Carbon::parse($label));
                    $gaps = $dates->slice(1)->values()
                        ->map(fn (Carbon $date, int $index): float => $dates[$index]->diffInDays($date));

                    /**
                     * Toute la fenêtre détenue, plus d'un an — le premier point tombe à la fin de
                     * la semaine du premier achat — et jamais deux points dans la même semaine.
                     */
                    return Carbon::parse('2026-01-01')->diffInDays($dates->first()) < 7
                        && $dates->first()->diffInDays($dates->last()) > 365
                        && $gaps->min() >= 5;
                })
            )
        );
});

it('expose les dividendes perçus sur la fiche', function () {
    $this->travelTo('2026-08-19 10:00:00');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dividends.receipts', 1)
            ->where('dividends.receipts.0.exDate', '2026-03-05')
            /**
             * Clôture et cast, comme `instrument.position.marketValue` ailleurs dans ce fichier :
             * les props traversent `json_encode()`, qui sérialise un flottant à fraction nulle sans
             * son « .0 », et `where()` compare strictement.
             */
            ->where('dividends.receipts.0.amount', fn ($v) => (float) $v === 5.0)
            ->where('dividends.totalReceived', fn ($v) => (float) $v === 5.0)
            ->where('dividends.yieldOnCost', fn ($v) => (float) $v === 0.63)
        );
});

it('rend un historique de dividendes vide sur un capitalisant', function () {
    $asset = Instrument::factory()->create(['name' => 'ACC']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('dividends.receipts', 0));
});

it('defers the analysis figures and loads them on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 80]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->missing('analysis')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('analysis.pru', fn ($v) => (float) $v === 80.0)
                ->where('analysis.pruGapPct', fn ($v) => (float) $v === 25.0)
                ->where('analysis.high52w', fn ($v) => (float) $v === 100.0)
            )
        );
});

it('omits the analysis when the instrument is not held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('analysis', null))
        );
});
