<?php

use App\Contexts\Portfolio\Enums\TransactionType;

it('exposes values and french labels', function () {
    expect(TransactionType::Buy->value)->toBe('buy')
        ->and(TransactionType::Sell->value)->toBe('sell')
        ->and(TransactionType::values())->toBe(['buy', 'sell', 'deposit', 'withdrawal', 'dividend'])
        ->and(TransactionType::Buy->getLabel())->toBe('Achat')
        ->and(TransactionType::Sell->getLabel())->toBe('Vente');
});

it('libelle les mouvements d\'espèces en français', function () {
    expect(TransactionType::Deposit->getLabel())->toBe('Versement')
        ->and(TransactionType::Withdrawal->getLabel())->toBe('Retrait')
        ->and(TransactionType::Dividend->getLabel())->toBe('Dividende');
});
