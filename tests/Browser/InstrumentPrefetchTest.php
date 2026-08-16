<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function userForPrefetch(): User
{
    User::query()->delete();

    return User::factory()->create();
}

function seedPrefetchInstrument(string $name, string $ticker): Instrument
{
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create([
        'name' => $name,
        'ticker' => $ticker,
    ]);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10)->format('Y-m-d'), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->format('Y-m-d'), 'close' => 120]);

    return $asset;
}

/**
 * Builds a script reporting whether the browser already requested the given path.
 */
function hasRequestedPath(string $path): string
{
    return "performance.getEntriesByType('resource').some(entry => new URL(entry.name).pathname === '{$path}')";
}

it('prefetches the instrument page when hovering a radar row', function () {
    $user = userForPrefetch();
    $asset = seedPrefetchInstrument('Alpha', 'ALP');

    $this->actingAs($user);

    $page = visit('/instruments')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$asset->id}")))->toBeFalse();

    $page->hover('[data-catalog-row] a')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$asset->id}")))->toBeTrue();
});

it('prefetches the instrument page when hovering a dashboard holding', function () {
    $user = userForPrefetch();
    $asset = seedPrefetchInstrument('Alpha', 'ALP');
    $wallet = Wallet::factory()->for($user)->create();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 80,
        'date' => now()->subMonth(),
    ]);

    $this->actingAs($user);

    $page = visit('/')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$asset->id}")))->toBeFalse();

    $page->hover('[data-holding-name]')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$asset->id}")))->toBeTrue();
});

it('prefetches the radar when hovering the breadcrumb from an instrument page', function () {
    $user = userForPrefetch();
    $asset = seedPrefetchInstrument('Alpha', 'ALP');

    $this->actingAs($user);

    $page = visit("/instruments/{$asset->id}")->assertSee('Alpha');

    expect($page->script(hasRequestedPath('/instruments')))->toBeFalse();

    $page->hover('nav[aria-label="Fil d\'Ariane"] a[href="/instruments"]')->wait(1);

    expect($page->script(hasRequestedPath('/instruments')))->toBeTrue();
});
