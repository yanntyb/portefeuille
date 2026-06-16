<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->adapter = new DatabaseAssetPriceAdapter(new EloquentPriceRepository);
    $this->instrument = Instrument::factory()->create();
});

it('retourne le prix courant (dernier close) en float', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01', 'close' => 10]);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01', 'close' => 42.5]);

    expect($this->adapter->getCurrentPrice($this->instrument->id))->toBe(42.5);
});

it('retourne null pour le prix courant sans données', function () {
    expect($this->adapter->getCurrentPrice($this->instrument->id))->toBeNull();
});

it('retourne un historique mappé en tableaux', function () {
    Price::factory()->create([
        'asset_id' => $this->instrument->id,
        'date' => '2026-02-01',
        'open' => 1, 'high' => 2, 'low' => 0.5, 'close' => 1.5, 'volume' => 100,
    ]);

    $history = $this->adapter->getPriceHistory($this->instrument->id, '2026-01-01');

    expect($history)->toBeInstanceOf(Collection::class)
        ->and($history)->toHaveCount(1)
        ->and($history->first())->toMatchArray([
            'date' => '2026-02-01',
            'close' => 1.5,
            'volume' => 100,
        ]);
});

it('filtre l’historique par date de fin', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-05-01']);

    expect($this->adapter->getPriceHistory($this->instrument->id, '2026-01-01', '2026-03-01'))
        ->toHaveCount(1);
});

it('supporte tout type d’instrument', function () {
    expect($this->adapter->supports(InstrumentType::Crypto))->toBeTrue();
});
