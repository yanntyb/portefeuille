<?php

use App\Contexts\RealEstate\Enums\ExpenseCategory;

it('exposes its values as strings', function () {
    expect(ExpenseCategory::values())->toContain('property_tax', 'works')
        ->and(ExpenseCategory::values())->toHaveCount(6);
});

it('labels every case in French', function () {
    expect(ExpenseCategory::PropertyTax->getLabel())->toBe('Taxe foncière')
        ->and(ExpenseCategory::CoOwnership->getLabel())->toBe('Copropriété')
        ->and(ExpenseCategory::Insurance->getLabel())->toBe('Assurance')
        ->and(ExpenseCategory::Management->getLabel())->toBe('Gestion')
        ->and(ExpenseCategory::Works->getLabel())->toBe('Travaux')
        ->and(ExpenseCategory::Other->getLabel())->toBe('Autre');
});
