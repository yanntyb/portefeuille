<?php

use App\Contexts\Valuation\Enums\ValuationRange;

it('maps each range to a month count', function () {
    expect(ValuationRange::OneMonth->months())->toBe(1)
        ->and(ValuationRange::SixMonths->months())->toBe(6)
        ->and(ValuationRange::OneYear->months())->toBe(12)
        ->and(ValuationRange::Max->months())->toBeNull();
});

it('resolves from a request value with a Max default', function () {
    expect(ValuationRange::fromRequest('6M'))->toBe(ValuationRange::SixMonths)
        ->and(ValuationRange::fromRequest(null))->toBe(ValuationRange::Max)
        ->and(ValuationRange::fromRequest('nope'))->toBe(ValuationRange::Max);
});
