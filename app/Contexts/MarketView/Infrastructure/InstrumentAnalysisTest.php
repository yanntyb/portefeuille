<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\MarketView\Ports\InstrumentAnalysisPort;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->analysis = app(InstrumentAnalysisPort::class);
});

/** Deux cent soixante séances montant de 1 € par jour : de quoi remplir MM200, RSI et ATR. */
function seedRisingPrices(int $assetId, int $sessions = 260, float $start = 100.0): void
{
    foreach (range(0, $sessions - 1) as $offset) {
        $close = $start + $offset;

        Price::factory()->create([
            'asset_id' => $assetId,
            'date' => Carbon::parse('2026-08-29')->subDays($sessions - 1 - $offset),
            'open' => $close,
            'high' => $close + 1,
            'low' => $close - 1,
            'close' => $close,
        ]);
    }
}

it('ne rend rien pour un actif que l\'utilisateur ne détient pas', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    seedRisingPrices($instrument->id, 5);

    expect($this->analysis->forAsset($user->id, $instrument->id))->toBeNull();
});

it('rend le prix de revient et son écart au dernier cours', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    /** La fixture achète 10 titres à 80 € et cote le dernier à 100 €. */
    expect($data->pru)->toBe(80.0)
        ->and($data->pruGapPct)->toBe(25.0);
});

it('rend la moyenne à deux cents séances et l\'écart du cours à cette moyenne', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    seedRisingPrices($instrument->id);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => 100,
    ]);

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    /** Clôtures 100 à 359 : la moyenne des 200 dernières vaut 259,5, le dernier cours 359. */
    expect($data->ma200)->toBe(259.5)
        ->and(round($data->ma200GapPct, 4))->toBe(round((359 - 259.5) / 259.5 * 100, 4))
        ->and($data->rsi14)->toBe(100.0)
        ->and($data->high52w)->toBe(359.0)
        ->and($data->high52wGapPct)->toBe(0.0);
});

it('laisse la moyenne longue à nul quand l\'historique est trop court', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    expect($data->ma200)->toBeNull()
        ->and($data->ma200GapPct)->toBeNull();
});

it('rend le poids de la position dans le portefeuille entier', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'AUTRE', 'ticker' => 'AUT']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => now(), 'close' => 300]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $other->id,
        'quantity' => 10,
        'avg_cost' => 300,
    ]);

    /** 1 000 € sur 4 000 € : la position pèse un quart du portefeuille. */
    expect($this->analysis->forAsset($user->id, $instrument->id)->portfolioWeightPct)->toBe(25.0);
});

it('garde le prix de revient et le poids quand aucun cours ne tombe dans la fenêtre de cinq ans', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();

    /**
     * Un seul cours, plus vieux que la fenêtre de cinq ans lue par `forAssetSince()` :
     * `GetPortfolioPositions` le trouve quand même via `latestClosesForAssets()`, qui n'a pas
     * cette borne, donc la position se valorise malgré une fenêtre vide.
     */
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->subYears(6),
        'close' => 100,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    /** Position unique du portefeuille : elle en pèse la totalité. */
    expect($data->pru)->toBe(80.0)
        ->and($data->portfolioWeightPct)->toBe(100.0)
        ->and($data->rsi14)->toBeNull();
});
