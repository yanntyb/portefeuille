<?php

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Services\TransactionFlow;

it('alourdit un achat de ses frais', function () {
    expect((new TransactionFlow)->of(TransactionType::Buy, 3.0, 80.0, 1.5, null))->toBe(241.5);
});

it('grève une vente de ses frais', function () {
    expect((new TransactionFlow)->of(TransactionType::Sell, 3.0, 80.0, 1.5, null))->toBe(238.5);
});

it('rend le montant brut d\'une opération sans frais', function () {
    expect((new TransactionFlow)->of(TransactionType::Buy, 10.0, 80.0, 0.0, null))->toBe(800.0)
        ->and((new TransactionFlow)->of(TransactionType::Sell, 10.0, 80.0, 0.0, null))->toBe(800.0);
});

it('rend le montant saisi d\'un mouvement d\'espèces, quantité et prix ignorés', function () {
    expect((new TransactionFlow)->of(TransactionType::Deposit, null, null, 0.0, 1000.0))->toBe(1000.0)
        ->and((new TransactionFlow)->of(TransactionType::Withdrawal, null, null, 0.0, 250.0))->toBe(250.0)
        ->and((new TransactionFlow)->of(TransactionType::Dividend, null, null, 0.0, 42.5))->toBe(42.5);
});

it('sort le montant d\'un achat, frais compris', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Buy, 10.0, 100.0, 5.0, null))->toBe(-1005.0);
});

it('fait entrer le produit d\'une vente, net de frais', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Sell, 10.0, 100.0, 5.0, null))->toBe(995.0);
});

it('fait entrer un versement et un dividende', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Deposit, null, null, 0.0, 1000.0))->toBe(1000.0)
        ->and((new TransactionFlow)->cashDelta(TransactionType::Dividend, null, null, 0.0, 42.5))->toBe(42.5);
});

it('sort un retrait, quel que soit le signe saisi', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Withdrawal, null, null, 0.0, 200.0))->toBe(-200.0)
        ->and((new TransactionFlow)->cashDelta(TransactionType::Withdrawal, null, null, 0.0, -200.0))->toBe(-200.0);
});
