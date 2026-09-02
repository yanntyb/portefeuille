<?php

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;

it('admet toutes les classes sans filtre', function () {
    $scope = HoldingScope::all();

    expect($scope->classes)->toBeNull()
        ->and($scope->walletId)->toBeNull()
        ->and($scope->admits(AssetClass::Equity))->toBeTrue()
        ->and($scope->admitsWallet(7))->toBeTrue();
});

it('n\'admet que les classes demandées', function () {
    $scope = HoldingScope::ofClasses([AssetClass::Equity]);

    expect($scope->admits(AssetClass::Equity))->toBeTrue()
        ->and($scope->admits(AssetClass::Crypto))->toBeFalse()
        ->and($scope->admitsWallet(7))->toBeTrue();
});

it('n\'admet que l\'enveloppe demandée', function () {
    $scope = HoldingScope::ofWallet(7);

    expect($scope->admitsWallet(7))->toBeTrue()
        ->and($scope->admitsWallet(8))->toBeFalse()
        ->and($scope->admits(AssetClass::Crypto))->toBeTrue();
});

it('cumule les deux filtres', function () {
    $scope = HoldingScope::of([AssetClass::Equity], 7);

    expect($scope->admits(AssetClass::Equity))->toBeTrue()
        ->and($scope->admits(AssetClass::Crypto))->toBeFalse()
        ->and($scope->admitsWallet(8))->toBeFalse();
});

/**
 * Le nom de cache est le seul endroit où un périmètre se traduit en chaîne : deux périmètres
 * distincts ne peuvent pas partager de suffixe, sous peine de servir la série de l'un à l'autre.
 */
it('rend un suffixe de cache distinct par périmètre', function () {
    expect(HoldingScope::all()->cacheKey())->toBe('')
        ->and(HoldingScope::ofClasses([AssetClass::Equity])->cacheKey())->toBe('.equity')
        ->and(HoldingScope::ofClasses([AssetClass::Equity, AssetClass::Crypto])->cacheKey())->toBe('.equity-crypto')
        ->and(HoldingScope::ofWallet(7)->cacheKey())->toBe('.enveloppe-7')
        ->and(HoldingScope::of([AssetClass::Equity], 7)->cacheKey())->toBe('.equity.enveloppe-7');
});

it('ne perd pas une classe demandée deux fois dans son nom de cache', function () {
    expect(HoldingScope::ofClasses([AssetClass::Equity, AssetClass::Equity])->cacheKey())->toBe('.equity');
});
