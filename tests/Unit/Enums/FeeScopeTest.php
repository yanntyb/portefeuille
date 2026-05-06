<?php

use App\Enums\FeeScope;

it('has total valuation case', function () {
    expect(FeeScope::TotalValuation->value)->toBe('total_valuation');
});

it('has unrealized gain case', function () {
    expect(FeeScope::UnrealizedGain->value)->toBe('unrealized_gain');
});

it('has realized gain case', function () {
    expect(FeeScope::RealizedGain->value)->toBe('realized_gain');
});

it('returns french label for total valuation', function () {
    expect(FeeScope::TotalValuation->getLabel())->toBe('Valeur totale');
});

it('returns french label for unrealized gain', function () {
    expect(FeeScope::UnrealizedGain->getLabel())->toBe('Plus-value latente');
});

it('returns french label for realized gain', function () {
    expect(FeeScope::RealizedGain->getLabel())->toBe('Plus-value réalisée');
});

it('can convert all cases to array', function () {
    $cases = FeeScope::cases();

    expect($cases)->toHaveCount(3)
        ->and($cases[0])->toBe(FeeScope::TotalValuation)
        ->and($cases[1])->toBe(FeeScope::UnrealizedGain)
        ->and($cases[2])->toBe(FeeScope::RealizedGain);
});
