<?php

use App\Contexts\Identity\Http\AuthenticateDefaultUser;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('records the line and sends the visitor back', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->from('/')
        ->post('/transactions', transactionPayload($wallet->id, $instrument->id))
        ->assertRedirect('/');

    /** `latest('id')` seul attraperait le versement déduit que l'observateur écrit derrière. */
    $transaction = Transaction::query()->where('type', 'buy')->latest('id')->first();

    expect($transaction->user_id)->toBe($user->id)
        ->and((float) $transaction->quantity)->toBe(5.0);

    /** La position suit sans qu'on la touche : l'observateur reprojette au `created`. */
    $holding = Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $holding->quantity)->toBe(15.0);
});

it('ignores a smuggled owner and a smuggled realized gain', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $intruder = User::factory()->create();

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, [
        'userId' => $intruder->id,
        'user_id' => $intruder->id,
        'realizedGain' => 9999,
        'realized_gain' => 9999,
    ]))->assertRedirect();

    /** `latest('id')` seul attraperait le versement déduit que l'observateur écrit derrière. */
    $transaction = Transaction::query()->where('type', 'buy')->latest('id')->first();

    /** `$guarded = ['id']` laisserait tout passer : c'est la Data d'entrée qui borne l'écriture. */
    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->realized_gain)->toBeNull();
});

it('refuses a date in the future', function () {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $before = Transaction::query()->count();

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, [
        'date' => now()->addDay()->format('Y-m-d'),
    ]))->assertSessionHasErrors('date');

    expect(Transaction::query()->count())->toBe($before);
});

it('refuses a quantity or a price that is not strictly positive', function (string $field, string $value) {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, [$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    ['quantity', '0'],
    ['quantity', '-1'],
    ['unitPrice', '0'],
    ['unitPrice', '-5'],
    ['fees', '-1'],
]);

it('refuses a wallet that belongs to somebody else', function () {
    ['instrument' => $instrument] = portfolioFixture();
    $stranger = Wallet::factory()->create(['name' => 'Ailleurs']);

    $this->post('/transactions', transactionPayload($stranger->id, $instrument->id))
        ->assertSessionHasErrors('walletId');
});

it('refuses an unknown asset and an unknown type', function () {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->post('/transactions', transactionPayload($wallet->id, 999_999))
        ->assertSessionHasErrors('assetId');

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, ['type' => 'donation']))
        ->assertSessionHasErrors('type');
});

it('refuses selling more than the wallet holds, and accepts selling what it holds', function () {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    /** La fixture détient 10 titres dans cette enveloppe. */
    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, [
        'type' => 'sell',
        'quantity' => '11',
    ]))->assertSessionHasErrors('quantity');

    $this->post('/transactions', transactionPayload($wallet->id, $instrument->id, [
        'type' => 'sell',
        'quantity' => '10',
    ]))->assertSessionHasNoErrors();
});

it('counts the stock wallet by wallet, not asset by asset', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Vide']);

    /** Les 10 titres de la fixture sont ailleurs : cette enveloppe-ci n'en détient aucun. */
    $this->post('/transactions', transactionPayload($empty->id, $instrument->id, [
        'type' => 'sell',
        'quantity' => '1',
    ]))->assertSessionHasErrors('quantity');
});

it('records a crypto buy in a share savings plan', function () {
    ['user' => $user] = portfolioFixture();
    $pea = Wallet::factory()->pea()->for($user)->create();
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create([
        'ticker' => 'BTC-EUR',
        'asset_class' => AssetClass::Crypto,
    ]);

    /**
     * Les règles d'enveloppe sont déclaratives : elles s'affichent, elles ne bloquent pas. L'app
     * dit ce qui est inéligible, elle n'empêche pas de saisir ce qui a réellement eu lieu.
     */
    $this->post('/transactions', transactionPayload($pea->id, $bitcoin->id))
        ->assertSessionHasNoErrors();
});

describe('mouvements d\'espèces', function () {
    beforeEach(function () {
        ['user' => $this->user, 'wallet' => $this->wallet, 'instrument' => $this->asset] = portfolioFixture();
    });

    it('enregistre un versement sans actif ni quantité', function () {
        $this->actingAs($this->user)
            ->post('/transactions', [
                'walletId' => $this->wallet->id,
                'date' => '2026-03-01',
                'type' => 'deposit',
                'amount' => 1000,
            ])
            ->assertRedirect();

        expect(Transaction::query()->where('type', TransactionType::Deposit)->where('auto', false)->count())->toBe(1);
    });

    it('refuse un versement sans montant', function () {
        $this->actingAs($this->user)
            ->post('/transactions', [
                'walletId' => $this->wallet->id,
                'date' => '2026-03-01',
                'type' => 'deposit',
            ])
            ->assertSessionHasErrors('amount');
    });

    it('refuse un achat sans quantité', function () {
        $this->actingAs($this->user)
            ->post('/transactions', [
                'walletId' => $this->wallet->id,
                'assetId' => $this->asset->id,
                'date' => '2026-03-01',
                'type' => 'buy',
                'unitPrice' => 100,
            ])
            ->assertSessionHasErrors('quantity');
    });

    it('refuse un retrait supérieur au solde de l\'enveloppe', function () {
        Transaction::factory()->deposit()->create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'date' => '2026-01-01',
            'amount' => 500,
        ]);

        $this->actingAs($this->user)
            ->post('/transactions', [
                'walletId' => $this->wallet->id,
                'date' => '2026-03-01',
                'type' => 'withdrawal',
                'amount' => 800,
            ])
            ->assertSessionHasErrors('amount');
    });
});

it('refuses to write anything on a database with no user', function () {
    $wallet = Wallet::factory()->create(['name' => 'Orphelin']);
    $instrument = Instrument::factory()->create(['ticker' => 'ORP.PA']);

    /**
     * `Wallet::factory()` a bien créé un utilisateur, donc le middleware d'auto-connexion en
     * trouve un ; c'est la déconnexion explicite qui met le cas à l'épreuve.
     */
    auth()->logout();

    $this->withoutMiddleware(AuthenticateDefaultUser::class)
        ->post('/transactions', transactionPayload($wallet->id, $instrument->id))
        ->assertForbidden();
});
