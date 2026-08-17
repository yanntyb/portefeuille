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
        ->assertScript("document.querySelectorAll('[data-holding-meta]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('titles the holdings section', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    seedHoldingLine($user, $wallet, ['name' => 'ACME', 'ticker' => 'ACM', 'quantity' => 10, 'avgCost' => 80, 'close' => 100]);

    $this->actingAs($user);

    visit('/')
        ->assertScript("document.querySelector('[data-section=holdings] h2').textContent.trim()", 'Positions')
        ->assertNoJavaScriptErrors();
});

it('keeps only the ten heaviest holdings, weighted against the whole portfolio', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    // Twelve lines worth 1 200 € down to 100 €, seeded lightest first so the order can only come from the sorting.
    foreach (range(1, 12) as $rank) {
        seedHoldingLine($user, $wallet, [
            'name' => sprintf('LINE%02d', $rank),
            'ticker' => sprintf('L%02d', $rank),
            'quantity' => $rank,
            'avgCost' => 80,
            'close' => 100,
        ]);
    }

    $this->actingAs($user);

    $textOf = fn (string $selector): string => "Array.from(document.querySelectorAll('{$selector}')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    $heaviestTen = collect(range(12, 3))
        ->map(fn (int $rank): string => sprintf('LINE%02d (L%02d)', $rank, $rank))
        ->implode('|');

    visit('/')
        ->assertScript("document.querySelectorAll('[data-holding-row]').length", 10)
        ->assertScript($textOf('[data-holding-name]'), $heaviestTen)
        // 1 200 € out of the 7 800 € total of the twelve lines, not out of the ten rendered ones.
        ->assertScript("document.querySelector('[data-holding-weight]').textContent.replace(/\\s+/g, ' ').trim()", '15,4 %')
        ->assertNoJavaScriptErrors();
});

it('links to the instruments list below the holdings, even without any holding', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    Wallet::factory()->for($user)->create();

    $this->actingAs($user);

    visit('/')
        ->assertScript("document.querySelectorAll('[data-holdings-all]').length", 1)
        ->assertScript("document.querySelector('[data-holdings-all]').getAttribute('href')", '/instruments')
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

it('spreads a holding over two visible lines so the narrow column keeps every column', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    seedHoldingLine($user, $wallet, ['name' => 'ACME', 'ticker' => 'ACM', 'quantity' => 10, 'avgCost' => 80, 'close' => 100]);

    $this->actingAs($user);

    visit('/')
        ->assertScript(
            "(() => {
                const row = document.querySelector('[data-holding-row]');
                const cells = ['name', 'value', 'gain-pct', 'bar', 'weight', 'gain']
                    .map((key) => row.querySelector('[data-holding-' + key + ']'));

                if (cells.some((cell) => cell === null || cell.getBoundingClientRect().width === 0)) {
                    return 'hidden column';
                }

                const value = row.querySelector('[data-holding-value]').getBoundingClientRect();
                const weight = row.querySelector('[data-holding-weight]').getBoundingClientRect();
                const trend = row.querySelector('[data-holding-trend]').getBoundingClientRect();
                const gain = row.querySelector('[data-holding-gain]').getBoundingClientRect();
                const pct = row.querySelector('[data-holding-gain-pct]').getBoundingClientRect();

                if (weight.top <= value.top) {
                    return 'one line';
                }

                const aligned = Math.round(trend.left) === Math.round(value.left)
                    && Math.round(trend.right) === Math.round(value.right)
                    && Math.round(gain.left) === Math.round(pct.left);

                return aligned ? 'two lines' : 'misaligned columns';
            })()",
            'two lines',
        )
        ->assertNoJavaScriptErrors();
});
