<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * @return array{user: User, instrument: Instrument}
 */
function seedBreadcrumbPortfolio(): array
{
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
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
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 80,
        'date' => now()->subMonth(),
    ]);

    return ['user' => $user, 'instrument' => $asset];
}

it('shows a sticky breadcrumb on the dashboard', function () {
    ['user' => $user] = seedBreadcrumbPortfolio();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Tableau de bord')
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertAttribute('nav[aria-label="Fil d\'Ariane"] [aria-current="page"]', 'aria-current', 'page')
        ->assertNoJavaScriptErrors();
});

/**
 * Every listed selector must start on the same horizontal axis as the breadcrumb.
 */
function alignmentScript(array $selectors): string
{
    $json = json_encode($selectors, JSON_THROW_ON_ERROR);

    return <<<JS
    (() => {
        const left = (selector) => Math.round(document.querySelector(selector).getBoundingClientRect().left);
        const reference = left('header nav > *');

        return {$json}.every((selector) => left(selector) === reference);
    })()
    JS;
}

it('aligns the instrument page content with the breadcrumb', function () {
    ['user' => $user, 'instrument' => $instrument] = seedBreadcrumbPortfolio();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript(alignmentScript(['main h1', 'main section p', 'main h2', 'main table th']), true);
});

it('aligns the catalogue page content with the breadcrumb', function () {
    ['user' => $user] = seedBreadcrumbPortfolio();

    $this->actingAs($user);

    visit('/instruments')
        ->assertScript(alignmentScript(['main table th']), true);
});

it('shows the full breadcrumb trail on an instrument page', function () {
    ['user' => $user, 'instrument' => $instrument] = seedBreadcrumbPortfolio();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Tableau de bord')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Instruments')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'ACME')
        ->assertNoJavaScriptErrors();
});
