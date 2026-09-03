<?php

use App\Contexts\PortfolioView\Services\ChartStep;
use App\Contexts\Valuation\Enums\ValuationGranularity;

it('garde le pas quotidien sans label et jusqu\'au trimestre', function () {
    expect((new ChartStep)->for([]))->toBe(ValuationGranularity::Day)
        ->and((new ChartStep)->for(['2026-01-01']))->toBe(ValuationGranularity::Day)
        ->and((new ChartStep)->for(['2026-01-01', '2026-04-03']))->toBe(ValuationGranularity::Day);
});

it('repasse au pas hebdomadaire au-delà de quatre-vingt-douze jours', function () {
    expect((new ChartStep)->for(['2026-01-01', '2026-04-04']))->toBe(ValuationGranularity::Week);
});
