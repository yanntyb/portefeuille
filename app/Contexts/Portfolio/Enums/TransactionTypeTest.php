<?php

use App\Contexts\Portfolio\Enums\TransactionType;

it('exposes values and french labels', function () {
    expect(TransactionType::Buy->value)->toBe('buy')
        ->and(TransactionType::Sell->value)->toBe('sell')
        ->and(TransactionType::values())->toBe(['buy', 'sell'])
        ->and(TransactionType::Buy->getLabel())->toBe('Achat')
        ->and(TransactionType::Sell->getLabel())->toBe('Vente');
});
