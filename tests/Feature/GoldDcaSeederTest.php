<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Database\Seeders\GoldDcaSeeder;

/**
 * `GoldDcaSeeder` et `BtcDcaSeeder` sont deux sous-classes de `FixedDcaSeeder` qui ne surchargent
 * que quatre constantes. La mécanique du DCA — un achat par mois, sans frais, la quantité au
 * huitième de décimale — est éprouvée une fois pour toutes par `BtcDcaSeederTest` ; ce fichier ne
 * garde que ce qui appartient en propre à l'or : ses quatre constantes, et sa tolérance à une
 * réponse vide du fournisseur.
 */
function fakeGoldYahoo(PythonResult $result): void
{
    $bulk = $result->ok()
        ? new PythonResult('ok', ['4GLD.DE' => $result->data])
        : $result;

    $fake = (new FakePythonRunner)->withResult(YahooScript::PricesBulk->path(), $bulk);
    app()->instance(PythonRunner::class, $fake);
}

it('câble ses quatre constantes sur l\'or physique', function () {
    fakeGoldYahoo(new PythonResult('ok', [[
        'date' => '2023-01-01', 'open' => 2500.0, 'high' => 2500.0,
        'low' => 2500.0, 'close' => 2500.0, 'volume' => 1000,
    ]]));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);

    $gold = Instrument::query()->where('ticker', '4GLD.DE')->first();

    expect($gold)->not->toBeNull()
        ->and($gold->type)->toBe(InstrumentType::Commodity)
        ->and($gold->name)->toBe('Or (Xetra-Gold)')
        ->and(Price::query()->where('asset_id', $gold->id)->count())->toBeGreaterThan(0)
        ->and(Wallet::query()->where('name', 'Portefeuille Or')->exists())->toBeTrue();
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeGoldYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);

    expect(Instrument::query()->where('ticker', '4GLD.DE')->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0);
});
