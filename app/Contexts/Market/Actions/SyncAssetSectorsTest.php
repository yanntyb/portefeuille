<?php

use App\Contexts\Market\Actions\SyncAssetSectors;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Market\Ports\SectorProviderPort;

/**
 * @param  array<string, array<int, SectorAllocationData>>  $allocationsByTicker
 */
function fakeSectorProvider(array $allocationsByTicker): SectorProviderPort
{
    return new class($allocationsByTicker) implements SectorProviderPort
    {
        /** @var list<string> */
        public array $calls = [];

        /** @param array<string, array<int, SectorAllocationData>> $allocationsByTicker */
        public function __construct(private array $allocationsByTicker) {}

        public function getSectorAllocations(string $symbol, InstrumentType $type): array
        {
            $this->calls[] = $symbol;

            return $this->allocationsByTicker[$symbol] ?? [];
        }

        public function supports(InstrumentType $type): bool
        {
            return in_array($type, [InstrumentType::Stock, InstrumentType::ETF]);
        }
    };
}

it('stores the sector allocations returned for every supported asset', function () {
    $etf = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);

    $synced = app(SyncAssetSectors::class, ['provider' => fakeSectorProvider([
        'PE500.PA' => [
            new SectorAllocationData(Sector::Technology, 0.37),
            new SectorAllocationData(Sector::Healthcare, 0.11),
        ],
    ])])();

    expect($synced)->toBe(['PE500.PA' => 2])
        ->and(SectorAllocation::query()->where('asset_id', $etf->id)->count())->toBe(2);
});

it('skips the asset types the provider does not support', function () {
    $crypto = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['ticker' => 'BTC-EUR']);
    $provider = fakeSectorProvider([]);

    $synced = app(SyncAssetSectors::class, ['provider' => $provider])();

    expect($synced)->toBe([])
        ->and($provider->calls)->toBe([])
        ->and(SectorAllocation::query()->where('asset_id', $crypto->id)->count())->toBe(0);
});

it('skips the assets without a ticker', function () {
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => null]);
    $provider = fakeSectorProvider([]);

    app(SyncAssetSectors::class, ['provider' => $provider])();

    expect($provider->calls)->toBe([]);
});

it('keeps the stored sectors when the provider returns nothing', function () {
    $etf = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);
    SectorAllocation::factory()->create([
        'asset_id' => $etf->id,
        'sector' => Sector::Technology,
        'weight' => 0.37,
    ]);

    $synced = app(SyncAssetSectors::class, ['provider' => fakeSectorProvider([])])();

    expect($synced)->toBe([])
        ->and(SectorAllocation::query()->where('asset_id', $etf->id)->count())->toBe(1);
});

it('syncs a single asset when an id is given', function () {
    $first = Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PE500.PA']);
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'PUST.PA']);

    $provider = fakeSectorProvider([
        'PE500.PA' => [new SectorAllocationData(Sector::Technology, 1.0)],
        'PUST.PA' => [new SectorAllocationData(Sector::Technology, 1.0)],
    ]);

    $synced = app(SyncAssetSectors::class, ['provider' => $provider])($first->id);

    expect($synced)->toBe(['PE500.PA' => 1])
        ->and($provider->calls)->toBe(['PE500.PA']);
});

it('returns nothing when the given asset does not exist', function () {
    expect(app(SyncAssetSectors::class, ['provider' => fakeSectorProvider([])])(404))->toBe([]);
});
