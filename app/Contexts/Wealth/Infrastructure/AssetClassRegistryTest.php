<?php

use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Ports\AssetClassPort;

function fakeAssetClass(string $key, float $value = 0.0): AssetClassPort
{
    return new class($key, $value) implements AssetClassPort
    {
        public function __construct(private string $keyName, private float $value) {}

        public function key(): string
        {
            return $this->keyName;
        }

        public function label(): string
        {
            return ucfirst($this->keyName);
        }

        public function href(): string
        {
            return '/'.$this->keyName;
        }

        public function color(): string
        {
            return 'value';
        }

        public function snapshotFor(int $userId): ClassSnapshotData
        {
            return new ClassSnapshotData(value: $this->value, invested: 0.0);
        }

        /** @return list<ClassSectorData> */
        public function sectorSlicesFor(int $userId): array
        {
            return [];
        }

        public function seriesFor(int $userId): ClassSeriesData
        {
            return ClassSeriesData::empty();
        }

        public function incomeLabel(): ?string
        {
            return null;
        }

        public function monthlyIncomeFor(int $userId): float
        {
            return 0.0;
        }
    };
}

it('serves the asset classes in the order they were declared', function () {
    $registry = new AssetClassRegistry([
        fakeAssetClass('equity'),
        fakeAssetClass('realEstate'),
        fakeAssetClass('crypto'),
    ]);

    expect(array_map(fn (AssetClassPort $class): string => $class->key(), $registry->all()))
        ->toBe(['equity', 'realEstate', 'crypto']);
});

it('serves nothing when no class is declared', function () {
    expect((new AssetClassRegistry([]))->all())->toBe([]);
});

it('derives one class per exposure, then the hand-written ones', function () {
    $keys = array_map(
        fn (AssetClassPort $class): string => $class->key(),
        app(AssetClassRegistry::class)->all(),
    );

    expect($keys)->toBe(['equity', 'bond', 'commodity', 'crypto', 'realEstate']);
});
