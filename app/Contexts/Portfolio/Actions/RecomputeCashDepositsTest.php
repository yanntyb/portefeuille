<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);
});

function cashBuy(float $quantity, float $price, string $date = '2026-03-03'): Transaction
{
    return Transaction::factory()->buy()->create([
        'user_id' => test()->user->id,
        'wallet_id' => test()->wallet->id,
        'asset_id' => test()->asset->id,
        'date' => $date,
        'quantity' => $quantity,
        'unit_price' => $price,
        'fees' => 0,
    ]);
}

function cashDeposits(): Collection
{
    return Transaction::query()->where('type', TransactionType::Deposit)->orderBy('date')->get();
}

it('déduit un versement du montant d\'un achat non financé', function () {
    cashBuy(10, 100);

    expect(cashDeposits())->toHaveCount(1)
        ->and((float) cashDeposits()->first()->amount)->toBe(1000.0)
        ->and(cashDeposits()->first()->auto)->toBeTrue()
        ->and(cashDeposits()->first()->date->format('Y-m-d'))->toBe('2026-03-03');
});

it('réécrit le versement déduit quand l\'achat change de montant', function () {
    $transaction = cashBuy(10, 100);
    $transaction->update(['quantity' => 12]);

    expect(cashDeposits())->toHaveCount(1)
        ->and((float) cashDeposits()->first()->amount)->toBe(1200.0);
});

it('efface le versement déduit quand l\'achat disparaît', function () {
    cashBuy(10, 100)->delete();

    expect(cashDeposits())->toHaveCount(0);
});

it('ne touche jamais un versement saisi à la main', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 10000,
        'auto' => false,
    ]);

    cashBuy(10, 100);

    expect(cashDeposits())->toHaveCount(1)
        ->and(cashDeposits()->first()->auto)->toBeFalse()
        ->and((float) cashDeposits()->first()->amount)->toBe(10000.0);
});

it('ne déduit rien quand une vente antérieure finance l\'achat', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 1000,
        'auto' => false,
    ]);

    cashBuy(10, 100, '2026-01-02');
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-02-01',
        'quantity' => 10,
        'unit_price' => 150,
        'fees' => 0,
    ]);
    cashBuy(10, 140, '2026-03-01');

    expect(cashDeposits()->where('auto', true))->toHaveCount(0);
});
