<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Dashboard with an empty overview when there is no data', function () {
    User::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 0.0)
            ->has('overview.holdings', 0)
            ->has('overview.allocation', 0)
        );
});

it('renders the Dashboard with the user portfolio overview', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 1000.0)
            ->where('overview.totalGain', fn ($value) => (float) $value === 200.0)
            ->has('overview.holdings', 1)
            ->has('overview.allocation', 1)
            ->where('overview.holdings.0.assetName', 'ACME')
            ->where('overview.holdings.0.assetId', $asset->id)
        );
});

it('sépare les propriétés différées par section, chaque groupe se chargeant seul', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            // Le graphe n'attend plus les secteurs ni les tendances : son groupe arrive seul.
            ->loadDeferredProps('evolution', fn (Assert $reload) => $reload
                ->has('evolutionSeries')
                ->missing('sectorBreakdown')
                ->missing('performances')
                ->missing('trends')
            )
            ->loadDeferredProps('secteurs', fn (Assert $reload) => $reload
                ->has('sectorBreakdown')
                ->missing('evolutionSeries')
            )
        );
});

it('defers the evolution series and loads it on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('evolutionSeries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('evolutionSeries.labels', 1)
                ->has('evolutionSeries.perAsset', 1)
                ->where('evolutionSeries.perAsset.0.name', 'ACME')
                ->has('evolutionSeries.perAsset.0.value')
                ->has('evolutionSeries.perAsset.0.invested')
            )
        );
});

it('samples the evolution series week by week, not day by day', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);

    foreach (range(0, 30) as $offset) {
        Price::factory()->create([
            'asset_id' => $asset->id,
            'date' => Carbon::parse('2026-01-01')->addDays($offset)->format('Y-m-d'),
            'close' => 100,
        ]);
    }

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('evolutionSeries.labels', function (Collection $labels): bool {
                    $dates = $labels->map(fn (string $label): Carbon => Carbon::parse($label));
                    $gaps = $dates->slice(1)->values()
                        ->map(fn (Carbon $date, int $index): float => $dates[$index]->diffInDays($date));

                    return $gaps->isNotEmpty() && $gaps->min() >= 5;
                })
            )
        );
});

it('defers the sector breakdown and loads it on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'ACME ETF']);
    SectorAllocation::factory()->create([
        'asset_id' => $asset->id,
        'sector' => Sector::Technology,
        'weight' => 1.0,
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('sectorBreakdown')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('sectorBreakdown', 1)
                ->where('sectorBreakdown.0.label', 'Technologie')
                ->where('sectorBreakdown.0.value', fn ($value) => (float) $value === 1000.0)
                ->where('sectorBreakdown.0.pct', fn ($value) => (float) $value === 100.0)
                ->has('sectorBreakdown.0.color')
            )
        );
});

it('defers the portfolio performances and loads them on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('performances')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('performances')
                ->where('performances.0.key', 'YTD')
                ->where('performances.0.startDate', '2026-01-01')
                ->has('performances.0.gain')
                ->has('performances.0.contributions')
                ->has('performances.0.valueStart')
            )
        );
});

it('ships the whole evolution history in one go, the zoom being client-side', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2024-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 120]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('valuationMonths')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('evolutionSeries.labels.0', '2024-01-01')
                ->missing('evolutionSeries.hasMore')
            )
        );
});

it('ignores a months query parameter, the window no longer being server-driven', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2024-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 120]);

    $this->get('/?months=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('evolutionSeries.labels.0', '2024-01-01')
            )
        );
});

it('defers the catalogue trends and loads them on demand', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'Trending Co']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('trends')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trends', 1)
                ->where('trends.0.assetId', $asset->id)
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});

it('accepts the range query param for the catalogue trends', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subMonths(6), 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/?range=1M')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});
