<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->missing('assetClass.hasIncome'));
})->with(AssetClass::cases());

/**
 * Rapatriée d'`AssetClassAnalysisControllerTest` : performances et secteurs sont revenus sur la
 * page d'exposition, en sections repliées, et gardent chacun leur groupe différé.
 */
it('sépare les groupes différés de la page, chaque groupe se chargeant seul', function () {
    cryptoFixture();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->loadDeferredProps('secteurs', fn (Assert $reload) => $reload
                ->has('sectorBreakdown')
                ->missing('performances')
            )
            ->loadDeferredProps('performances', fn (Assert $reload) => $reload
                ->has('performances')
                ->missing('sectorBreakdown')
            )
        );
});

it('offers sectors on equity alone', function () {
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ];

    $equity = $this->get(route('classes.equity'), $headers);
    $equity->assertOk();
    expect($equity->json('deferredProps'))->toHaveKeys(['tendances', 'evolution', 'performances', 'secteurs']);

    $crypto = $this->get(route('classes.crypto'), $headers);
    $crypto->assertOk();
    expect($crypto->json('deferredProps'))
        ->toHaveKey('performances')
        ->not->toHaveKey('secteurs')
        ->not->toHaveKey('revenus');
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
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

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
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

it('diffère l’analyse de la classe et la charge à la demande', function () {
    portfolioFixture();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Index')
            ->missing('basketAnalysis')
            ->loadDeferredProps('analyse', fn (Assert $reload) => $reload
                ->has('basketAnalysis.instruments.0.label')
                ->has('basketAnalysis.correlations')
                ->has('basketAnalysis.maxDrawdown')
                ->has('basketAnalysis.high52wGapPct')
                ->missing('performances')
            )
        );
});
