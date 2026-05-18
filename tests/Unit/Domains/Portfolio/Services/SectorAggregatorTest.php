<?php

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Portfolio\Services\SectorAggregator;
use Mockery\MockInterface;

function makePriceRepository(array $prices = []): MockInterface
{
    return mock(AssetPriceRepositoryInterface::class, function (MockInterface $mock) use ($prices) {
        foreach ($prices as $assetId => $close) {
            $price = new AssetPrice(['close' => $close]);
            $mock->shouldReceive('findLatestForAsset')->with($assetId)->andReturn($price);
        }
    });
}

it('returns empty data for empty securities collection', function () {
    $priceRepository = makePriceRepository();
    $aggregator = new SectorAggregator($priceRepository);
    $result = $aggregator->buildStackedSectorData(collect());

    expect($result)->toHaveKey('datasets')
        ->and($result)->toHaveKey('labels')
        ->and($result['datasets'])->toBeEmpty()
        ->and($result['labels'])->toBeEmpty();
});

it('skips securities with zero quantity', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'Stock';
    $security->total_quantity = 0;
    $security->sectors = collect([(object) ['sector' => Sector::Technology, 'weight' => 1.0]]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['datasets'])->toBeEmpty()
        ->and($result['labels'])->toBeEmpty();
});

it('skips securities with no latest price', function () {
    $priceRepository = makePriceRepository();
    $priceRepository->shouldReceive('findLatestForAsset')->with(1)->andReturn(null);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'Stock';
    $security->total_quantity = 10;
    $security->sectors = collect([(object) ['sector' => Sector::Technology, 'weight' => 1.0]]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['datasets'])->toBeEmpty()
        ->and($result['labels'])->toBeEmpty();
});

it('calculates valuation correctly', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'AAPL';
    $security->total_quantity = 10;
    $security->sectors = collect([(object) ['sector' => Sector::Technology, 'weight' => 1.0]]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['datasets'])->toHaveCount(1)
        ->and($result['datasets'][0]['label'])->toBe('AAPL')
        ->and($result['datasets'][0]['data'][0])->toBe(100.0);
});

it('distributes valuation by sector weight', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'Fund';
    $security->total_quantity = 10;
    $security->sectors = collect([
        (object) ['sector' => Sector::Technology, 'weight' => 0.6],
        (object) ['sector' => Sector::Healthcare, 'weight' => 0.4],
    ]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['labels'])->toHaveCount(2)
        ->and($result['datasets'][0]['data'][0])->toBe(60.0)
        ->and($result['datasets'][0]['data'][1])->toBe(40.0);
});

it('sorts sectors by total value descending', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'Tech Stock';
    $security->total_quantity = 100;
    $security->sectors = collect([
        (object) ['sector' => Sector::Technology, 'weight' => 0.8],
        (object) ['sector' => Sector::Healthcare, 'weight' => 0.2],
    ]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['labels'][0])->toBe(Sector::Technology->getLabel())
        ->and($result['labels'][1])->toBe(Sector::Healthcare->getLabel());
});

it('handles multiple securities with different sectors', function () {
    $priceRepository = makePriceRepository([1 => 100, 2 => 200]);
    $aggregator = new SectorAggregator($priceRepository);

    $security1 = new stdClass;
    $security1->id = 1;
    $security1->name = 'Stock A';
    $security1->total_quantity = 10;
    $security1->sectors = collect([(object) ['sector' => Sector::Technology, 'weight' => 1.0]]);

    $security2 = new stdClass;
    $security2->id = 2;
    $security2->name = 'Stock B';
    $security2->total_quantity = 5;
    $security2->sectors = collect([(object) ['sector' => Sector::Healthcare, 'weight' => 1.0]]);

    $result = $aggregator->buildStackedSectorData(collect([$security1, $security2]));

    expect($result['datasets'])->toHaveCount(2)
        ->and($result['labels'])->toHaveCount(2);
});

it('calculates percentages correctly', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'Fund';
    $security->total_quantity = 100;
    $security->sectors = collect([
        (object) ['sector' => Sector::Technology, 'weight' => 0.6],
        (object) ['sector' => Sector::Healthcare, 'weight' => 0.4],
    ]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['datasets'][0]['data'][0])->toBe(60.0)
        ->and($result['datasets'][0]['data'][1])->toBe(40.0);
});

it('includes chart metadata in datasets', function () {
    $priceRepository = makePriceRepository([1 => 100]);
    $aggregator = new SectorAggregator($priceRepository);

    $security = new stdClass;
    $security->id = 1;
    $security->name = 'AAPL';
    $security->total_quantity = 10;
    $security->sectors = collect([(object) ['sector' => Sector::Technology, 'weight' => 1.0]]);

    $result = $aggregator->buildStackedSectorData(collect([$security]));

    expect($result['datasets'][0])->toHaveKey('label')
        ->and($result['datasets'][0])->toHaveKey('data')
        ->and($result['datasets'][0])->toHaveKey('backgroundColor')
        ->and($result['datasets'][0])->toHaveKey('borderWidth')
        ->and($result['datasets'][0]['borderWidth'])->toBe(0);
});
