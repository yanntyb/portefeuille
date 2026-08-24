<?php

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;

it('étiquette chaque source en français', function () {
    expect(IncomeSource::Dividend->getLabel())->toBe('Dividendes')
        ->and(IncomeSource::Rent->getLabel())->toBe('Loyers');
});

it('liste ses valeurs', function () {
    expect(IncomeSource::values())->toBe(['dividend', 'rent']);
});

it('pays dividends on equity alone', function () {
    expect(IncomeSource::forAssetClass(AssetClass::Equity))->toBe(IncomeSource::Dividend)
        ->and(IncomeSource::forAssetClass(AssetClass::Bond))->toBeNull()
        ->and(IncomeSource::forAssetClass(AssetClass::Commodity))->toBeNull()
        ->and(IncomeSource::forAssetClass(AssetClass::Crypto))->toBeNull();
});

it('never maps two exposures onto the same source', function () {
    $sources = array_filter(array_map(
        fn (AssetClass $class): ?IncomeSource => IncomeSource::forAssetClass($class),
        AssetClass::cases(),
    ));

    expect($sources)->toHaveCount(count(array_unique($sources, SORT_REGULAR)));
});
