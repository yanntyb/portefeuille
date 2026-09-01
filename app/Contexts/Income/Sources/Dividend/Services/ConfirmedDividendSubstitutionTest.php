<?php

use App\Contexts\Income\Sources\Dividend\Datas\ConfirmedDividendData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Services\ConfirmedDividendSubstitution;

it('substitue le net encaissé au brut dérivé, sans jamais garder les deux', function () {
    $receipts = [
        new DividendReceiptData(assetId: 1, walletId: 7, exDate: '2026-03-12', quantity: 100.0, amountPerShare: 0.5, amount: 50.0),
    ];
    $confirmed = [
        new ConfirmedDividendData(assetId: 1, walletId: 7, exDate: '2026-03-12', amount: 34.9),
    ];

    $merged = (new ConfirmedDividendSubstitution)->apply($receipts, $confirmed);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]->amount)->toBe(34.9)
        ->and($merged[0]->quantity)->toBe(100.0)
        ->and($merged[0]->amountPerShare)->toBe(0.349);
});

/**
 * Le signal d'échec, autrefois muet : sans dérivé retrouvé, la substitution sortait « 0 titre ×
 * 0 € » à côté d'un montant bien réel, ce qui se lit comme un fait mesuré. `null` dit l'inconnu.
 */
it('dit l\'inconnu plutôt que zéro quand le détachement dérivé manque', function () {
    $confirmed = [
        new ConfirmedDividendData(assetId: 1, walletId: 7, exDate: '2026-03-12', amount: 34.9),
    ];

    $merged = (new ConfirmedDividendSubstitution)->apply([], $confirmed);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]->amount)->toBe(34.9)
        ->and($merged[0]->quantity)->toBeNull()
        ->and($merged[0]->amountPerShare)->toBeNull();
});
