<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * @param  array{name: string, ticker: string, quantity: float, avgCost: float, close: float}  $line
 */
function seedHoldingLine(User $user, Wallet $wallet, array $line): void
{
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create([
        'name' => $line['name'],
        'ticker' => $line['ticker'],
    ]);

    Price::factory()->create([
        'asset_id' => $asset->id,
        'date' => '2026-01-01',
        'close' => $line['close'],
    ]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $line['quantity'],
        'avg_cost' => $line['avgCost'],
    ]);
}

it('lists the holdings from the heaviest to the lightest, with their weight and gain', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    // Seeded lightest first, so a passing order assertion can only come from the component sorting.
    seedHoldingLine($user, $wallet, ['name' => 'BETA', 'ticker' => 'BET', 'quantity' => 5, 'avgCost' => 40, 'close' => 50]);
    seedHoldingLine($user, $wallet, ['name' => 'ACME', 'ticker' => 'ACM', 'quantity' => 10, 'avgCost' => 80, 'close' => 100]);

    $this->actingAs($user);

    $textOf = fn (string $selector): string => "Array.from(document.querySelectorAll('{$selector}')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit('/')
        ->assertScript("document.querySelectorAll('[data-holding-row]').length", 2)
        ->assertScript($textOf('[data-holding-name]'), 'ACME (ACM)|BETA (BET)')
        ->assertScript($textOf('[data-holding-value]'), '1 000 €|250 €')
        ->assertScript($textOf('[data-holding-weight]'), '80,0 %|20,0 %')
        ->assertScript($textOf('[data-holding-gain]'), '+200 €|+50 €')
        ->assertScript($textOf('[data-holding-gain-pct]'), '+25,0 %|+25,0 %')
        ->assertScript($textOf('[data-holding-meta]'), 'Stock · 10 × 100,00 €|Stock · 5 × 50,00 €')
        ->assertNoJavaScriptErrors();
});

it('drops the tabular header the holdings used to render', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    seedHoldingLine($user, $wallet, ['name' => 'ACME', 'ticker' => 'ACM', 'quantity' => 10, 'avgCost' => 80, 'close' => 100]);

    $this->actingAs($user);

    visit('/')
        ->assertScript("document.querySelectorAll('[data-section=holdings] thead').length", 0)
        ->assertNoJavaScriptErrors();
});
