<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Infrastructure\LaravelSeriesCache;

/**
 * Une instance par appel : l'empreinte n'est mémoïsée que le temps d'une requête, et c'est
 * bien d'une requête à la suivante que l'invalidation doit se voir.
 */
function rememberSerie(int $userId, int &$calls): string
{
    return (new LaravelSeriesCache)->remember('serie', $userId, function () use (&$calls): string {
        $calls++;

        return 'calculé';
    });
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create();
    $this->asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $this->asset->id, 'date' => '2026-01-01', 'close' => 100]);
});

function buyFor(object $context): Transaction
{
    return Transaction::factory()->buy()->create([
        'user_id' => $context->user->id,
        'wallet_id' => $context->wallet->id,
        'asset_id' => $context->asset->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2026-01-01',
    ]);
}

it('ne recalcule pas une série tant que rien ne bouge', function () {
    $calls = 0;

    expect(rememberSerie($this->user->id, $calls))->toBe('calculé');
    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(1);
});

it('recalcule dès qu\'un nouveau cours est connu', function () {
    $calls = 0;
    rememberSerie($this->user->id, $calls);

    Price::factory()->create(['asset_id' => $this->asset->id, 'date' => '2026-02-01', 'close' => 120]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'une transaction de l\'utilisateur change', function () {
    $transaction = buyFor($this);
    $calls = 0;
    rememberSerie($this->user->id, $calls);

    $transaction->update(['quantity' => 42]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule après la suppression d\'une transaction', function () {
    $transaction = buyFor($this);
    $calls = 0;
    rememberSerie($this->user->id, $calls);

    $transaction->delete();

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule quand une transaction change d\'enveloppe sans rien changer d\'autre', function () {
    $elsewhere = Wallet::factory()->for($this->user)->create();
    $transaction = buyFor($this);
    $calls = 0;
    rememberSerie($this->user->id, $calls);

    /**
     * Le cas que les sommes de quantité, de prix et de frais ne voient pas : rien ne change de ce
     * qu'elles agrègent, et `updated_at` ne descend pas sous la seconde. Sans `sum(wallet_id)`
     * dans l'empreinte, la série resterait celle d'avant le déménagement.
     */
    $transaction->update(['wallet_id' => $elsewhere->id]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('périme la série quand un achat devient une vente', function () {
    $calls = 0;
    $transaction = buyFor($this);
    rememberSerie($this->user->id, $calls);

    /**
     * Ni la cardinalité ni les sommes de quantité, de prix ou d'actif ne bougent : sans le
     * comptage des ventes dans l'empreinte, la série périmée serait servie.
     */
    $transaction->update(['type' => TransactionType::Sell]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

/**
 * L'empreinte ne comptait que les ventes : un retrait basculé en versement — même montant, même
 * date, même enveloppe, et aucune ligne déduite de part et d'autre — ne bougeait aucun agrégat,
 * et pourtant le signe du mouvement s'inverse.
 */
it('périme la série quand un retrait devient un versement', function () {
    $calls = 0;
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    /** Le solde couvre le retrait, et le couvrira encore après la bascule : rien ne se déduit. */
    $withdrawal = Transaction::factory()->withdrawal()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-01', 'amount' => 200,
    ]);
    rememberSerie($this->user->id, $calls);

    $withdrawal->update(['type' => TransactionType::Deposit]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('périme la série quand le montant d\'un versement change', function () {
    $calls = 0;
    $deposit = Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    rememberSerie($this->user->id, $calls);

    $deposit->update(['amount' => 1500]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('ne mélange pas les séries de deux utilisateurs', function () {
    $other = User::factory()->create();
    $calls = 0;

    rememberSerie($this->user->id, $calls);
    rememberSerie($other->id, $calls);

    expect($calls)->toBe(2);
});
