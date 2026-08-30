<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('renders AssetClass/Index with an empty overview when there is no data', function () {
    User::factory()->create();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->where('overview.totalValue', fn ($value) => (float) $value === 0.0)
            ->has('overview.holdings', 0)
        );
});

it('renders AssetClass/Index with the user portfolio overview', function () {
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->where('overview.totalValue', fn ($value) => (float) $value === 1000.0)
            ->where('overview.totalGain', fn ($value) => (float) $value === 200.0)
            ->has('overview.holdings', 1)
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            // Le graphe n'attend plus les tendances : son groupe arrive seul.
            ->loadDeferredProps('evolution', fn (Assert $reload) => $reload
                ->has('evolutionSeries')
                ->missing('trends')
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
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
        ->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
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

    $this->get('/actions?months=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('evolutionSeries.labels.0', '2024-01-01')
            )
        );
});

it('defers the trends of the held instruments and loads them on demand', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'Trending Co']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    // Un instrument que personne ne détient n'a pas d'étincelle à porter : il reste hors des tendances.
    Instrument::factory()->create(['name' => 'Ignored Co']);

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('trends')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trends', 1)
                ->where('trends.0.assetId', $asset->id)
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});

it('diffère les opérations de l\'exposition, chaque ligne nommant son actif', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $crypto = Instrument::factory()->create(['name' => 'Bitcoin', 'asset_class' => AssetClass::Crypto]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'fees' => 1, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $crypto->id,
        'quantity' => 1, 'unit_price' => 30000, 'date' => '2026-02-01',
    ]);

    $this->actingAs($user)
        ->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('transactions')
            /** Le groupe est à part : le dépli de la section ne réveille pas les autres. */
            ->loadDeferredProps('transactions', fn (Assert $reload) => $reload
                ->has('transactions', 1)
                ->where('transactions.0.assetName', 'ACME')
                /** Les frais entrent dans le montant de l'achat, une seule fois. */
                ->where('transactions.0.total', fn ($total) => (float) $total === 1001.0)
                ->missing('evolutionSeries')
            )
        );
});
