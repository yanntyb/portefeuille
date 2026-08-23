<?php

use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;

it('porte la page liste et une fiche par position détenue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['instruments']['list'])->toHaveKeys([
        'overview', 'trends', 'performances', 'evolutionSeries', 'sectorBreakdown', 'income', 'annualIncome',
    ])
        ->and($snapshot['instruments']['byId'])->toHaveKey($instrument->id)
        ->and($snapshot['instruments']['byId'][$instrument->id])->toHaveKeys([
            'instrument', 'performances', 'priceHistory', 'valuation', 'dividends',
        ]);
});

it('range la crypto à part, sans dividendes sur ses fiches', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['crypto']['byId'])->toHaveKey($bitcoin->id)
        ->and($snapshot['crypto']['byId'][$bitcoin->id])->not->toHaveKey('dividends')
        ->and($snapshot['instruments']['byId'])->not->toHaveKey($bitcoin->id);
});
