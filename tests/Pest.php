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

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser', '../app/Contexts');

pest()->extend(Tests\TestCase::class)
    ->in('../app/Shared');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Portefeuille minimal mais complet : un utilisateur, un portefeuille, un instrument coté deux
 * fois, secteurisé, et une position achetée. Remplace le bloc recopié dans chaque fichier
 * Browser.
 *
 * Le secteur est nécessaire à la fiche instrument : sa section de répartition sectorielle ne se
 * rend plus du tout quand l'instrument n'a aucun `SectorAllocation`, elle ne se contente pas d'un
 * état vide.
 *
 * @param  array{name?: string, ticker?: string, quantity?: float, avgCost?: float, close?: float}  $overrides
 * @return array{user: User, wallet: Wallet, instrument: Instrument}
 */
function portfolioFixture(array $overrides = []): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $instrument = Instrument::factory()
        ->ofType(InstrumentType::Stock)
        ->create([
            'name' => $overrides['name'] ?? 'ACME',
            'ticker' => $overrides['ticker'] ?? 'ACM',
        ]);

    $close = $overrides['close'] ?? 100;

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now(), 'close' => $close]);
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->startOfYear(),
        'close' => $close * 0.8,
    ]);

    SectorAllocation::factory()->create([
        'asset_id' => $instrument->id,
        'sector' => Sector::Technology,
        'weight' => 1.0,
    ]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => $overrides['quantity'] ?? 10,
        'avg_cost' => $overrides['avgCost'] ?? 80,
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => $overrides['quantity'] ?? 10,
        'unit_price' => $overrides['avgCost'] ?? 80,
        'date' => '2026-01-01',
    ]);

    return ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument];
}

/**
 * Trois ans de cours quotidiens par défaut : le plancher d'un an n'est observable que sur un
 * historique plus long que lui.
 *
 * @return array{user: User, wallet: Wallet, instrument: Instrument}
 */
function denseHistoryFixture(int $days = 1095): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME']);

    foreach (range(0, $days) as $offset) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => now()->subDays($days - $offset)->format('Y-m-d'),
            'close' => 90 + sin($offset / 20) * 20,
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'quantity' => 10, 'unit_price' => 80, 'date' => now()->subDays($days)->format('Y-m-d'),
    ]);

    return ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument];
}

/**
 * Une position dont les secteurs sont pondérés, pour éprouver le repli de la liste sectorielle.
 *
 * @param  array<string, float>  $sectors  Clé : valeur d'un cas de `Sector`. Valeur : poids entre 0 et 1.
 */
function holdingWithSectors(User $user, string $name, float $close, array $sectors): void
{
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => $name]);

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => $close,
    ]);

    foreach ($sectors as $sector => $weight) {
        SectorAllocation::factory()->create([
            'asset_id' => $instrument->id,
            'sector' => Sector::from($sector),
            'weight' => $weight,
        ]);
    }
}

/** Amplitude de la fenêtre visible, en pourcentage de l'historique, publiée par le graphe en attribut. */
function zoomWindowSpan(Pest\Browser\Api\PendingAwaitablePage $page, string $section): float
{
    $window = (string) $page->script("document.querySelector('[data-section={$section}] [data-chart]').getAttribute('data-zoom-window')");
    [$start, $end] = array_map('floatval', explode('-', $window));

    return $end - $start;
}

/** Molette sur le graphe : vers l'avant on zoome, vers l'arrière on dézoome. */
function scrollChart(Pest\Browser\Api\PendingAwaitablePage $page, string $section, int $deltaY): void
{
    $page->script("(() => {
        const chart = document.querySelector('[data-section={$section}] [data-chart]');
        const box = chart.getBoundingClientRect();
        chart.querySelector('svg').dispatchEvent(new WheelEvent('wheel', {
            deltaY: {$deltaY},
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 3,
            bubbles: true,
            cancelable: true,
        }));
    })()");
}
