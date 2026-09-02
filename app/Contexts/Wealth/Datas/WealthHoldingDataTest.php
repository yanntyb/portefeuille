<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\PortfolioView\Datas\HoldingRowData;
use App\Contexts\Wealth\Datas\WealthHoldingData;

/**
 * Les trois jumelles doivent rendre le même JSON, clé pour clé et dans le même ordre : le front
 * lit `HoldingLine` sans savoir laquelle l'a produite, et l'instantané hors-ligne publie
 * `sha1(json_encode($body))`, qu'un ordre différent ferait retélécharger à tous les clients.
 */
it('rend exactement les clés de ses deux jumelles, dans le même ordre', function () {
    $arguments = [
        'assetId' => 1,
        'assetName' => 'ACME',
        'ticker' => 'ACM',
        'type' => InstrumentType::Stock,
        'assetClass' => AssetClass::Equity,
        'walletId' => 2,
        'walletName' => 'PEA',
        'accountType' => AccountType::Pea,
        'quantity' => 10.0,
        'avgCost' => 80.0,
        'lastPrice' => 100.0,
        'marketValue' => 1000.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
    ];

    $wealth = (new WealthHoldingData(...$arguments))->jsonSerialize();

    expect(array_keys($wealth))->toBe(array_keys((new HoldingLineData(...$arguments))->jsonSerialize()))
        ->and(array_keys($wealth))->toBe(array_keys((new HoldingRowData(...$arguments))->jsonSerialize()))
        ->and($wealth)->toBe((new HoldingRowData(...$arguments))->jsonSerialize());
});
