<?php

use App\Contexts\Valuation\Enums\ValuationGranularity;

it('buckets a date by granularity', function () {
    expect(ValuationGranularity::Day->bucketKey('2026-03-15'))->toBe('2026-03-15')
        ->and(ValuationGranularity::Month->bucketKey('2026-03-15'))->toBe('2026-03')
        ->and(ValuationGranularity::Week->bucketKey('2026-03-15'))->toBe(ValuationGranularity::Week->bucketKey('2026-03-09'));
});

it('resolves from a request value with a Month default', function () {
    expect(ValuationGranularity::fromRequest('week'))->toBe(ValuationGranularity::Week)
        ->and(ValuationGranularity::fromRequest(null))->toBe(ValuationGranularity::Month)
        ->and(ValuationGranularity::fromRequest('nope'))->toBe(ValuationGranularity::Month);
});
