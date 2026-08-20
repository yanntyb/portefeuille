<?php

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Sources\Dividend\DividendIncomeSource;
use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use App\Contexts\Income\Sources\Rent\RentIncomeSource;

it('maps schedule receipts to income receipts labeled by property', function () {
    $port = new class implements RentSchedulePort
    {
        public function receiptsFor(int $userId): array
        {
            return [new RentReceiptData(month: '2026-03-01', amount: 500.0, propertyName: 'T2 Lyon 7e')];
        }

        public function projectedAnnualFor(int $userId): float
        {
            return 6000.0;
        }
    };

    $source = new RentIncomeSource($port);
    $receipts = $source->receiptsFor(1);

    expect($source->source())->toBe(IncomeSource::Rent)
        ->and($receipts)->toHaveCount(1)
        ->and($receipts[0]->source)->toBe(IncomeSource::Rent)
        ->and($receipts[0]->date->toDateString())->toBe('2026-03-01')
        ->and($receipts[0]->amount)->toBe(500.0)
        ->and($receipts[0]->assetId)->toBeNull()
        ->and($receipts[0]->label)->toBe('T2 Lyon 7e')
        ->and($source->projectedAnnualFor(1))->toBe(6000.0);
});

it('is tagged into the income source registry', function () {
    $registry = app(IncomeSourceRegistry::class);

    $reflection = new ReflectionProperty($registry, 'sources');
    $sources = collect($reflection->getValue($registry))
        ->map(fn ($source): string => $source::class);

    expect($sources->all())->toContain(
        DividendIncomeSource::class,
        RentIncomeSource::class,
    );
});
