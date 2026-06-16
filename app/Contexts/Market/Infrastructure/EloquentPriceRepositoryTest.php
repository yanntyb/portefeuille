<?php

use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->repository = new EloquentPriceRepository;
    $this->instrument = Instrument::factory()->create();
});

it('retourne le dernier prix par date', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);

    expect($this->repository->latestForAsset($this->instrument->id)?->date->toDateString())
        ->toBe('2026-03-01');
});

it('retourne null quand aucun prix', function () {
    expect($this->repository->latestForAsset($this->instrument->id))->toBeNull();
});

it('trouve un prix à une date donnée', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-10']);

    expect($this->repository->forAssetOnDate($this->instrument->id, Carbon::parse('2026-02-10')))
        ->not->toBeNull();
});

it('retourne les prix depuis une date, triés', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);

    $prices = $this->repository->forAssetSince($this->instrument->id, Carbon::parse('2026-02-01'));

    expect($prices)->toHaveCount(2)
        ->and($prices->first()->date->toDateString())->toBe('2026-02-01');
});

it('retourne les prix de plusieurs assets', function () {
    $other = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2026-02-01']);

    expect($this->repository->forAssets([$this->instrument->id, $other->id], Carbon::parse('2026-01-01')))
        ->toHaveCount(2);
});

it('filtre les ids ayant un prix depuis une date', function () {
    $other = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2025-01-01']);

    $ids = $this->repository->filterAssetIdsHavingPriceSince(
        [$this->instrument->id, $other->id],
        Carbon::parse('2026-01-01'),
    );

    expect($ids)->toBe([$this->instrument->id]);
});
