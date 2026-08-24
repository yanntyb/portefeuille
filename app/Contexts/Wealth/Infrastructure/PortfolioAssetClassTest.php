<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Wealth\Infrastructure\PortfolioAssetClass;

it('describes itself from its exposure', function () {
    $commodity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Commodity]);

    expect($commodity->key())->toBe('commodity')
        ->and($commodity->label())->toBe('Matières premières')
        ->and($commodity->href())->toBe('/matieres-premieres')
        ->and($commodity->color())->toBe('commodity')
        ->and($commodity->incomeLabel())->toBeNull();
});

it('labels the income of an exposure that distributes', function () {
    $equity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Equity]);

    expect($equity->incomeLabel())->toBe('Dividendes');
});
