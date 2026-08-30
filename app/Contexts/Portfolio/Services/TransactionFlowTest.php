<?php

use App\Contexts\Portfolio\Services\TransactionFlow;

it('alourdit un achat de ses frais', function () {
    expect((new TransactionFlow)->of(3.0, 80.0, 1.5, false))->toBe(241.5);
});

it('grève une vente de ses frais', function () {
    expect((new TransactionFlow)->of(3.0, 80.0, 1.5, true))->toBe(238.5);
});

it('rend le montant brut d\'une opération sans frais', function () {
    expect((new TransactionFlow)->of(10.0, 80.0, 0.0, false))->toBe(800.0)
        ->and((new TransactionFlow)->of(10.0, 80.0, 0.0, true))->toBe(800.0);
});
