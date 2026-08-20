<?php

use App\Contexts\RealEstate\Enums\ExpenseCategory;

it('exposes its values as strings', function () {
    expect(ExpenseCategory::values())->toContain('property_tax', 'works')
        ->and(ExpenseCategory::values())->toHaveCount(6);
});

it('labels every case in French', function () {
    foreach (ExpenseCategory::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});
