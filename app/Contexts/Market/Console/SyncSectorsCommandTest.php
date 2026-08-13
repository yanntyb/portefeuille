<?php

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Market\Ports\SectorProviderPort;

it('stores the sectors and reports one line per ticker', function () {
    $etf = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturn([
            new SectorAllocationData(Sector::Technology, 0.37),
            new SectorAllocationData(Sector::Healthcare, 0.11),
        ]);
    });

    $this->artisan('market:sync-sectors')
        ->expectsOutputToContain('PE500.PA : 2 secteurs')
        ->assertSuccessful();

    expect(SectorAllocation::query()->where('asset_id', $etf->id)->count())->toBe(2);
});

it('warns when no sector could be fetched', function () {
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturn([]);
    });

    $this->artisan('market:sync-sectors')
        ->expectsOutputToContain('Aucun secteur récupéré.')
        ->assertSuccessful();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);
    $second = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PUST.PA']);

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturn([
            new SectorAllocationData(Sector::Technology, 1.0),
        ]);
    });

    $this->artisan('market:sync-sectors', ['--asset' => $first->id])->assertSuccessful();

    expect(SectorAllocation::query()->where('asset_id', $first->id)->count())->toBe(1)
        ->and(SectorAllocation::query()->where('asset_id', $second->id)->count())->toBe(0);
});
