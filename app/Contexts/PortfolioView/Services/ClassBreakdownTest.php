<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\PortfolioView\Services\ClassBreakdown;

function ligne(AssetClass $class, ?float $marketValue): HoldingLineData
{
    return new HoldingLineData(
        assetId: 1, assetName: 'X', ticker: null, type: InstrumentType::Stock, assetClass: $class,
        walletId: 1, walletName: 'PEA', accountType: AccountType::Pea,
        quantity: 1.0, avgCost: null, lastPrice: null, marketValue: $marketValue, gain: null, gainPct: null,
    );
}

it('somme chaque classe et la range de la plus lourde à la plus légère', function () {
    $slices = (new ClassBreakdown)->of([
        ligne(AssetClass::Equity, 300.0),
        ligne(AssetClass::Crypto, 600.0),
        ligne(AssetClass::Equity, 100.0),
    ]);

    expect(array_map(fn ($s) => [$s->key, $s->value, $s->share], $slices))->toBe([
        ['crypto', 600.0, 60.0],
        ['equity', 400.0, 40.0],
    ])->and($slices[0]->label)->toBe(AssetClass::Crypto->getLabel());
});

it('compte une ligne sans valeur pour zéro et ne rend rien sur un total nul', function () {
    expect((new ClassBreakdown)->of([ligne(AssetClass::Equity, null)]))->toBe([])
        ->and((new ClassBreakdown)->of([]))->toBe([]);
});
