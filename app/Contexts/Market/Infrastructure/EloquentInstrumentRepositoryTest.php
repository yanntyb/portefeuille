<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Models\Instrument;

beforeEach(function () {
    $this->repository = new EloquentInstrumentRepository;
});

it('trouve un instrument par id', function () {
    $instrument = Instrument::factory()->create();

    expect($this->repository->findById($instrument->id)?->id)->toBe($instrument->id);
});

it('retourne null pour un id inconnu', function () {
    expect($this->repository->findById(999))->toBeNull();
});

it('trouve les instruments par type', function () {
    Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Instrument::factory()->ofType(InstrumentType::ETF)->create();

    $stocks = $this->repository->findByType(InstrumentType::Stock);

    expect($stocks)->toHaveCount(1)
        ->and($stocks->first()->type)->toBe(InstrumentType::Stock);
});

it('retourne tous les instruments', function () {
    Instrument::factory()->count(3)->create();

    expect($this->repository->findAll())->toHaveCount(3);
});

it('persiste un instrument', function () {
    $instrument = Instrument::factory()->make();

    $this->repository->save($instrument);

    expect($instrument->exists)->toBeTrue()
        ->and(Instrument::query()->count())->toBe(1);
});
