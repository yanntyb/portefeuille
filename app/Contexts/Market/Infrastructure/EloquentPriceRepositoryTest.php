<?php

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->repository = new EloquentPriceRepository;
    $this->instrument = Instrument::factory()->create();
});

it('returns the latest price by date', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);

    expect($this->repository->latestForAsset($this->instrument->id)?->date->toDateString())
        ->toBe('2026-03-01');
});

it('returns null when there is no price', function () {
    expect($this->repository->latestForAsset($this->instrument->id))->toBeNull();
});

it('finds a price on a given date', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-10']);

    expect($this->repository->forAssetOnDate($this->instrument->id, Carbon::parse('2026-02-10')))
        ->not->toBeNull();
});

it('returns prices since a date, sorted', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);

    $prices = $this->repository->forAssetSince($this->instrument->id, Carbon::parse('2026-02-01'));

    expect($prices)->toHaveCount(2)
        ->and($prices->first()->date->toDateString())->toBe('2026-02-01');
});

it('returns prices for multiple assets', function () {
    $other = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-02-01']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2026-02-01']);

    expect($this->repository->forAssets([$this->instrument->id, $other->id], Carbon::parse('2026-01-01')))
        ->toHaveCount(2);
});

it('filters ids having a price since a date', function () {
    $other = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-03-01']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2025-01-01']);

    $ids = $this->repository->filterAssetIdsHavingPriceSince(
        [$this->instrument->id, $other->id],
        Carbon::parse('2026-01-01'),
    );

    expect($ids)->toBe([$this->instrument->id]);
});

it('inserts the given prices', function () {
    $written = $this->repository->upsertForAsset($this->instrument->id, [
        new PriceData(date: '2026-01-01', close: 10.0, open: 9.0, high: 11.0, low: 8.0, volume: 100),
        new PriceData(date: '2026-01-02', close: 12.0),
    ]);

    expect($written)->toBe(2)
        ->and(Price::query()->where('asset_id', $this->instrument->id)->count())->toBe(2);
});

it('overwrites an existing price on the same date', function () {
    Price::factory()->create([
        'asset_id' => $this->instrument->id,
        'date' => '2026-01-01',
        'close' => 10.0,
    ]);

    $this->repository->upsertForAsset($this->instrument->id, [
        new PriceData(date: '2026-01-01', close: 42.5),
    ]);

    expect(Price::query()->where('asset_id', $this->instrument->id)->count())->toBe(1)
        ->and((float) $this->repository->latestForAsset($this->instrument->id)->close)->toBe(42.5);
});

it('writes nothing for an empty list', function () {
    expect($this->repository->upsertForAsset($this->instrument->id, []))->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});
