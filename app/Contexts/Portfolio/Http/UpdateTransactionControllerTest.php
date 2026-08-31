<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('corrects the line and reprojects the position', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->from('/')
        ->put("/transactions/{$transaction->id}", transactionPayload($wallet->id, $instrument->id, [
            'quantity' => '12',
            'unitPrice' => '80',
            'fees' => '0',
        ]))
        ->assertRedirect('/');

    $holding = Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $transaction->fresh()->quantity)->toBe(12.0)
        ->and((float) $holding->quantity)->toBe(12.0);
});

it('reprojects the wallet the line came from when it moves', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $elsewhere = Wallet::factory()->for($user)->create(['name' => 'Ailleurs']);
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->put("/transactions/{$transaction->id}", transactionPayload($elsewhere->id, $instrument->id, [
        'quantity' => '10',
        'unitPrice' => '80',
        'fees' => '0',
    ]))->assertRedirect();

    /** L'ancienne enveloppe se vide, donc sa ligne de position disparaît. */
    expect(Holding::query()->where('wallet_id', $wallet->id)->where('asset_id', $instrument->id)->exists())
        ->toBeFalse()
        ->and((float) Holding::query()->where('wallet_id', $elsewhere->id)->where('asset_id', $instrument->id)->first()->quantity)
        ->toBe(10.0);
});

it('answers 404 on the line of another user', function () {
    ['wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    ['user' => $stranger] = portfolioFixture(['name' => 'Globex', 'ticker' => 'GBX']);
    $theirs = Transaction::query()->where('user_id', $stranger->id)->sole();
    $quantityBefore = $theirs->quantity;

    /**
     * Corps valide pour l'utilisateur courant, identifiant appartenant à un autre : c'est le seul
     * moyen d'atteindre le 404, car la requête validée passe AVANT le contrôleur — un portefeuille
     * étranger dans le corps rendrait 422 sans jamais chercher la ligne.
     *
     * 404 et non 403 : le code ne dit pas que la ligne existe.
     */
    $this->put("/transactions/{$theirs->id}", transactionPayload($wallet->id, $instrument->id, [
        'quantity' => '3',
        'unitPrice' => '80',
        'fees' => '0',
    ]))->assertNotFound();

    expect($theirs->fresh()->quantity)->toBe($quantityBefore);
});

it('leaves the owner of the line alone', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $intruder = User::factory()->create();
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->put("/transactions/{$transaction->id}", transactionPayload($wallet->id, $instrument->id, [
        'quantity' => '10',
        'unitPrice' => '80',
        'fees' => '0',
        'userId' => $intruder->id,
        'user_id' => $intruder->id,
    ]))->assertRedirect();

    expect($transaction->fresh()->user_id)->toBe($user->id);
});

it('lets a sell keep its own quantity out of the stock it checks against', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $sell = Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'unit_price' => 120,
        'fees' => 0,
        'date' => '2026-03-01',
    ]);

    $payload = fn (string $quantity): array => transactionPayload($wallet->id, $instrument->id, [
        'type' => 'sell',
        'quantity' => $quantity,
        'unitPrice' => '120',
        'fees' => '0',
        'date' => '2026-03-01',
    ]);

    /**
     * 10 titres achetés, 4 déjà vendus par cette même ligne. Sans l'exclusion de soi-même, porter
     * la vente à 8 se comparerait aux 6 titres restants et serait refusée à tort.
     */
    $this->put("/transactions/{$sell->id}", $payload('8'))->assertSessionHasNoErrors();

    $this->put("/transactions/{$sell->id}", $payload('11'))->assertSessionHasErrors('quantity');
});

it('never reaches the controller with a non numeric id', function () {
    ['user' => $user] = portfolioFixture();
    $before = Transaction::query()->where('user_id', $user->id)->sole()->quantity;

    /**
     * `whereNumber` empêche la route de correspondre. Le rendu d'exception de `bootstrap/app.php`
     * renvoie alors vers l'accueil, faute de route matchée — pas un 404, mais surtout : pas une
     * écriture. C'est ce que la contrainte protège.
     */
    $this->put('/transactions/abc', [])->assertRedirect('/');

    expect(Transaction::query()->where('user_id', $user->id)->sole()->quantity)->toBe($before);
});
