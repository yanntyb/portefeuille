<?php

use App\Contexts\Valuation\Enums\ValuationRange;

it('maps each range to a month count', function () {
    expect(ValuationRange::OneMonth->months())->toBe(1)
        ->and(ValuationRange::SixMonths->months())->toBe(6)
        ->and(ValuationRange::OneYear->months())->toBe(12)
        ->and(ValuationRange::Max->months())->toBeNull();
});
