<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Savings;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\AssetMetaDTO;

it('maps id, name, type, ticker and isin from Stock model', function (): void {
    $stock = Stock::factory()
        ->withInfos(fn (StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'AAPL',
            'isin' => 'US0378331005',
        ]))
        ->create(['name' => 'Apple Inc']);

    $dto = AssetMetaDTO::fromModel($stock);

    expect($dto->id)->toBe($stock->id)
        ->and($dto->name)->toBe('Apple Inc')
        ->and($dto->type)->toBe(AssetType::Stock)
        ->and($dto->ticker)->toBe('AAPL')
        ->and($dto->isin)->toBe('US0378331005');
});

it('returns null ticker and isin when asset has no infos', function (): void {
    $savings = Savings::factory()->create(['name' => 'Livret A']);

    $dto = AssetMetaDTO::fromModel($savings);

    expect($dto->ticker)->toBeNull()
        ->and($dto->isin)->toBeNull();
});
