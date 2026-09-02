<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

function holdIn(Wallet $wallet, InstrumentType $type, float $close, float $qty, float $avgCost): Instrument
{
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $wallet->user_id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $qty,
        'avg_cost' => $avgCost,
    ]);

    return $asset;
}

it('regroupe les positions par enveloppe et totalise chacune', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 50, qty: 4, avgCost: 50);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->walletId)->toBe($pea->id)
        ->and($lines[0]->marketValue)->toBe(1000.0)
        ->and($lines[0]->gain)->toBe(200.0)
        ->and($lines[0]->gainPct)->toBe(25.0)
        ->and($lines[0]->accountType)->toBe(AccountType::Pea)
        ->and($lines[0]->taxRegimeLabel)->toBe(AccountType::Pea->taxRegimeLabel())
        ->and($lines[1]->walletId)->toBe($cto->id)
        ->and($lines[1]->marketValue)->toBe(200.0)
        ->and($lines[1]->gain)->toBe(0.0);
});

it('porte le courtier du portefeuille, qui tient le compte', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create(['broker' => 'IBKR']);
    $cto = Wallet::factory()->for($user)->cto()->create(['broker' => null]);

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 50, qty: 4, avgCost: 50);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines[0]->broker)->toBe('IBKR')
        ->and($lines[1]->broker)->toBeNull();
});

it('rend un pourcentage nul, et non zéro, sur une enveloppe à coût nul', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $cto->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => null,
    ]);

    expect(app(GetAccountBreakdown::class)($user)[0]->gainPct)->toBeNull();
});

it('compte l\'ancienneté et la maturité du PEA, et rien sans date d\'ouverture', function () {
    Carbon::setTestNow('2026-08-31');
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create(['opened_at' => '2019-06-01']);
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 10, qty: 1, avgCost: 10);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines[0]->ageInYears)->toBe(7)
        ->and($lines[0]->maturityYears)->toBe(5)
        ->and($lines[1]->ageInYears)->toBeNull()
        ->and($lines[1]->maturityYears)->toBeNull();
});

it('convertit l\'ancienneté sans dépréciation PHP, même sur une date d\'ouverture non ronde', function () {
    Carbon::setTestNow('2026-08-31');
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create(['opened_at' => '2019-06-01']);

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);

    $deprecations = [];
    set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
        $deprecations[] = $errstr;

        return true;
    }, E_DEPRECATED);

    $ageInYears = app(GetAccountBreakdown::class)($user)[0]->ageInYears;

    restore_error_handler();

    // Du 1er juin 2019 au 31 août 2026 : 7 ans révolus, tronqués, pas arrondis.
    expect($deprecations)->toBe([])
        ->and($ageInYears)->toBe(7);
});

it('signale une position que l\'enveloppe n\'admet pas', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    $crypto = holdIn($pea, InstrumentType::Crypto, close: 50, qty: 1, avgCost: 40);
    holdIn($cto, InstrumentType::Crypto, close: 50, qty: 1, avgCost: 40);

    $lines = app(GetAccountBreakdown::class)($user);
    $byWallet = array_column(
        array_map(fn ($line): array => [$line->walletId, $line], $lines),
        1,
        0,
    );

    expect($byWallet[$pea->id]->ineligibleAssetNames)->toBe([$crypto->name])
        ->and($byWallet[$cto->id]->ineligibleAssetNames)->toBe([]);
});

it('ne rend aucune ligne pour une enveloppe vide', function () {
    $user = User::factory()->create();
    Wallet::factory()->for($user)->cto()->create();

    expect(app(GetAccountBreakdown::class)($user))->toBe([]);
});

it('porte le solde d\'espèces de chaque enveloppe, à zéro sans mouvement', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($cto, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);

    expect(app(GetAccountBreakdown::class)($user)[0]->cashBalance)->toBe(0.0);
});

it('affiche une enveloppe sans aucune position tant qu\'elle porte des espèces', function () {
    $user = User::factory()->create();
    $cashOnly = Wallet::factory()->for($user)->cto()->create();

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $cashOnly->id,
        'date' => '2026-01-01',
        'amount' => 5000,
    ]);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->walletId)->toBe($cashOnly->id)
        ->and($lines[0]->marketValue)->toBe(0.0)
        ->and($lines[0]->cashBalance)->toBe(5000.0);
});

/**
 * Le coût de revient et le gain déjà encaissé de chaque enveloppe, les deux repères qui suivent
 * partout le grand chiffre. Le réalisé se lit sur les ventes du compte, jamais sur ses positions :
 * un actif soldé n'a plus de ligne `Holding`, et son aller-retour disparaîtrait du bilan.
 */
it('porte le coût de revient et le gain réalisé de chaque enveloppe', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 50, qty: 4, avgCost: 50);

    /**
     * L'aller-retour porte sur un second titre, entièrement soldé : sa position a disparu, et son
     * gain ne se lit donc plus que sur les ventes. `TransactionObserver` pose `realized_gain` —
     * dix titres achetés à 80, revendus à 120.
     */
    $soldOut = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $pea->id, 'asset_id' => $soldOut->id,
        'quantity' => 10, 'unit_price' => 80, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $pea->id, 'asset_id' => $soldOut->id,
        'quantity' => 10, 'unit_price' => 120, 'date' => '2026-02-01',
    ]);

    $lines = collect(app(GetAccountBreakdown::class)($user))->keyBy('walletId');

    expect($lines[$pea->id]->cost)->toBe(800.0)
        ->and($lines[$pea->id]->realizedGain)->toBe(400.0)
        ->and($lines[$cto->id]->cost)->toBe(200.0)
        ->and($lines[$cto->id]->realizedGain)->toBe(0.0);
});
