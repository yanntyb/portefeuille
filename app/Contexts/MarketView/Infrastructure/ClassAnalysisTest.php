<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\MarketView\Infrastructure\ClassAnalysis;
use App\Contexts\MarketView\Ports\ClassAnalysisPort;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->analysis = app(ClassAnalysisPort::class);
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create();
});

/**
 * Un instrument détenu, coté chaque jour jusqu'à aujourd'hui, acheté en une fois à sa première
 * séance. L'achat n'est pas décoratif : la distance au plus-haut se mesure sur la valorisation de
 * la poche, que les transactions construisent — un instrument sans transaction ne vaut rien.
 *
 * @param  list<float>  $closes  Clôtures dans l'ordre chronologique, la dernière datée d'aujourd'hui.
 */
function classInstrument(
    string $ticker,
    array $closes,
    float $quantity = 1.0,
    AssetClass $assetClass = AssetClass::Equity,
): Instrument {
    $instrument = Instrument::factory()->create([
        'name' => $ticker,
        'ticker' => $ticker,
        'asset_class' => $assetClass,
    ]);

    foreach (array_values($closes) as $offset => $close) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => Carbon::today()->subDays(count($closes) - 1 - $offset),
            'close' => $close,
        ]);
    }

    Holding::factory()->create([
        'user_id' => test()->user->id,
        'wallet_id' => test()->wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => $quantity,
        'avg_cost' => $closes[0],
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => test()->user->id,
        'wallet_id' => test()->wallet->id,
        'asset_id' => $instrument->id,
        'date' => Carbon::today()->subDays(count($closes) - 1),
        'quantity' => $quantity,
        'unit_price' => $closes[0],
    ]);

    return $instrument;
}

it('range les instruments du plus lourd au plus léger', function () {
    classInstrument('PETIT', [10.0, 12.0], quantity: 1);
    classInstrument('GROS', [100.0, 120.0], quantity: 10);

    $data = $this->analysis->forClass($this->user->id, AssetClass::Equity);

    expect(array_map(fn ($line) => $line->label, $data->instruments))->toBe(['GROS', 'PETIT']);
});

it('rend une matrice carrée, symétrique et parfaite sur sa diagonale', function () {
    classInstrument('AAA', array_map(fn (int $day): float => 100.0 + $day, range(0, 59)));
    classInstrument('BBB', array_map(fn (int $day): float => 50.0 + $day / 2, range(0, 59)));

    $data = $this->analysis->forClass($this->user->id, AssetClass::Equity);

    expect($data->correlations)->toHaveCount(2)
        ->and($data->correlations[0])->toHaveCount(2)
        ->and($data->correlations[0][0])->toBe(1.0)
        ->and(round($data->correlations[0][1], 6))->toBe(1.0)
        ->and($data->correlations[0][1])->toBe($data->correlations[1][0]);
});

it('ne garde que les huit plus gros poids de la classe', function () {
    foreach (range(1, 9) as $rank) {
        classInstrument('I'.$rank, [100.0, 110.0], quantity: $rank);
    }

    $data = $this->analysis->forClass($this->user->id, AssetClass::Equity);

    expect($data->instruments)->toHaveCount(ClassAnalysis::MAX_INSTRUMENTS)
        ->and($data->correlations)->toHaveCount(ClassAnalysis::MAX_INSTRUMENTS)
        ->and(array_map(fn ($line) => $line->label, $data->instruments))
        ->not->toContain('I1');
});

it('mesure la chute maximale de la poche', function () {
    classInstrument('AAA', [100.0, 120.0, 90.0]);

    expect($this->analysis->forClass($this->user->id, AssetClass::Equity)->maxDrawdown)
        ->toBe(25.0);
});

it('situe la poche sous son plus-haut', function () {
    classInstrument('AAA', [100.0, 120.0, 90.0]);

    expect($this->analysis->forClass($this->user->id, AssetClass::Equity)->high52wGapPct)
        ->toBe(-25.0);
});

it('mesure la distance au plus-haut sur la valorisation, allégements compris', function () {
    /**
     * Le cours ne bouge pas de la semaine : l'indice du panier reste plat et ne verrait aucune
     * chute. La poche, elle, a été allégée de moitié hier — c'est ce que la ligne doit dire.
     */
    $instrument = classInstrument('AAA', [100.0, 100.0, 100.0], quantity: 2.0);

    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $instrument->id,
        'date' => Carbon::today()->subDay(),
        'quantity' => 1.0,
        'unit_price' => 100.0,
    ]);

    expect($this->analysis->forClass($this->user->id, AssetClass::Equity)->high52wGapPct)
        ->toBe(-50.0);
});

it('pèse chaque instrument dans l’indice selon sa place dans la poche', function () {
    /** Trois quarts sur un titre qui perd 20 %, un quart sur un titre étale : la poche perd 15 %. */
    classInstrument('GROS', [100.0, 80.0], quantity: 37.5);
    classInstrument('PETIT', [100.0, 100.0], quantity: 10);

    expect($this->analysis->forClass($this->user->id, AssetClass::Equity)->maxDrawdown)
        ->toBe(15.0);
});

it('ignore les instruments des autres expositions', function () {
    classInstrument('ACTION', [100.0, 120.0]);
    classInstrument('BITCOIN', [1000.0, 900.0], assetClass: AssetClass::Crypto);

    $data = $this->analysis->forClass($this->user->id, AssetClass::Equity);

    expect(array_map(fn ($line) => $line->label, $data->instruments))->toBe(['ACTION']);
});

it('rend une analyse vide sur une exposition que rien ne peuple', function () {
    $data = $this->analysis->forClass($this->user->id, AssetClass::Bond);

    expect($data->instruments)->toBe([])
        ->and($data->correlations)->toBe([])
        ->and($data->maxDrawdown)->toBeNull()
        ->and($data->high52wGapPct)->toBeNull();
});
