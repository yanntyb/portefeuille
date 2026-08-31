<?php

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

it('enregistre un versement sans actif ni quantité', function () {
    $deposit = Transaction::factory()->deposit()->create(['amount' => 1000]);

    expect($deposit->type)->toBe(TransactionType::Deposit)
        ->and($deposit->asset_id)->toBeNull()
        ->and($deposit->quantity)->toBeNull()
        ->and($deposit->unit_price)->toBeNull()
        ->and((float) $deposit->amount)->toBe(1000.0)
        ->and($deposit->auto)->toBeFalse();
});

it('marque une ligne déduite par le système', function () {
    expect(Transaction::factory()->deposit()->auto()->create(['amount' => 500])->auto)->toBeTrue();
});

it('enregistre un dividende porté par son actif', function () {
    $dividend = Transaction::factory()->dividend()->create(['amount' => 42.5]);

    expect($dividend->type)->toBe(TransactionType::Dividend)
        ->and($dividend->asset_id)->not->toBeNull()
        ->and((float) $dividend->amount)->toBe(42.5);
});
