<?php

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;

beforeEach(function () {
    $this->repository = new EloquentSectorRepository;
    $this->instrument = Instrument::factory()->create();
});

it('writes the sector allocations of an asset', function () {
    $this->repository->replaceForAsset($this->instrument->id, [
        new SectorAllocationData(Sector::Technology, 0.6),
        new SectorAllocationData(Sector::Healthcare, 0.4),
    ]);

    $stored = SectorAllocation::query()
        ->where('asset_id', $this->instrument->id)
        ->get()
        ->mapWithKeys(fn (SectorAllocation $allocation) => [$allocation->sector->value => (float) $allocation->weight]);

    expect($stored)->toHaveCount(2)
        ->and($stored[Sector::Technology->value])->toBe(0.6)
        ->and($stored[Sector::Healthcare->value])->toBe(0.4);
});

it('drops the sectors that are no longer returned', function () {
    SectorAllocation::factory()->create([
        'asset_id' => $this->instrument->id,
        'sector' => Sector::Energy,
        'weight' => 1.0,
    ]);

    $this->repository->replaceForAsset($this->instrument->id, [
        new SectorAllocationData(Sector::Technology, 1.0),
    ]);

    $sectors = SectorAllocation::query()->where('asset_id', $this->instrument->id)->pluck('sector');

    expect($sectors->all())->toBe([Sector::Technology]);
});

it('clears the sectors of an asset when given an empty list', function () {
    SectorAllocation::factory()->create([
        'asset_id' => $this->instrument->id,
        'sector' => Sector::Energy,
        'weight' => 1.0,
    ]);

    $this->repository->replaceForAsset($this->instrument->id, []);

    expect(SectorAllocation::query()->where('asset_id', $this->instrument->id)->count())->toBe(0);
});

it('leaves the sectors of the other assets untouched', function () {
    $other = Instrument::factory()->create();
    SectorAllocation::factory()->create([
        'asset_id' => $other->id,
        'sector' => Sector::Energy,
        'weight' => 1.0,
    ]);

    $this->repository->replaceForAsset($this->instrument->id, [
        new SectorAllocationData(Sector::Technology, 1.0),
    ]);

    expect(SectorAllocation::query()->where('asset_id', $other->id)->count())->toBe(1);
});

it('returns the allocations of the given assets', function () {
    $other = Instrument::factory()->create();
    $ignored = Instrument::factory()->create();

    SectorAllocation::factory()->create(['asset_id' => $this->instrument->id, 'sector' => Sector::Technology]);
    SectorAllocation::factory()->create(['asset_id' => $other->id, 'sector' => Sector::Energy]);
    SectorAllocation::factory()->create(['asset_id' => $ignored->id, 'sector' => Sector::Utilities]);

    $allocations = $this->repository->forAssets([$this->instrument->id, $other->id]);

    expect($allocations)->toHaveCount(2)
        ->and($allocations->pluck('asset_id')->all())->not->toContain($ignored->id);
});

it('returns an empty collection when no asset id is given', function () {
    SectorAllocation::factory()->create(['asset_id' => $this->instrument->id, 'sector' => Sector::Technology]);

    expect($this->repository->forAssets([]))->toBeEmpty();
});
