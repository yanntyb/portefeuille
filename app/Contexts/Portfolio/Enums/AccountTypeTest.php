<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\AccountType;

it('rend les valeurs de tous les cas', function () {
    expect(AccountType::values())->toBe(['pea', 'cto']);
});

it('nomme chaque enveloppe en français', function () {
    expect(AccountType::Pea->getLabel())->toBe('PEA')
        ->and(AccountType::Cto->getLabel())->toBe('Compte-titres');
});

it('affiche un régime d\'imposition par enveloppe', function () {
    expect(AccountType::Pea->taxRegimeLabel())->toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %')
        ->and(AccountType::Cto->taxRegimeLabel())->toBe('Flat tax 30 %');
});

it('ne donne une maturité qu\'aux enveloppes qui en ont une', function () {
    expect(AccountType::Pea->maturityYears())->toBe(5)
        ->and(AccountType::Cto->maturityYears())->toBeNull();
});

it('restreint le PEA aux actions et n\'admet rien d\'autre', function () {
    expect(AccountType::Pea->allowedAssetClasses())->toBe([AssetClass::Equity])
        ->and(AccountType::Pea->admits(AssetClass::Equity))->toBeTrue()
        ->and(AccountType::Pea->admits(AssetClass::Bond))->toBeFalse()
        ->and(AccountType::Pea->admits(AssetClass::Commodity))->toBeFalse()
        ->and(AccountType::Pea->admits(AssetClass::Crypto))->toBeFalse();
});

it('n\'impose aucune restriction au compte-titres', function () {
    expect(AccountType::Cto->allowedAssetClasses())->toBeNull();

    foreach (AssetClass::cases() as $class) {
        expect(AccountType::Cto->admits($class))->toBeTrue();
    }
});
