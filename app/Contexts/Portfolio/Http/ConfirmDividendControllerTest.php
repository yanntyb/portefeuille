<?php

use App\Contexts\Market\Models\Dividend;
use App\Contexts\Portfolio\Models\Transaction;

beforeEach(function () {
    ['user' => $this->user, 'wallet' => $this->wallet, 'instrument' => $this->instrument] = portfolioFixture();

    Dividend::query()->create([
        'asset_id' => $this->instrument->id,
        'ex_date' => '2026-02-01',
        'amount_per_share' => 0.5,
    ]);
});

/** @return array<string, mixed> */
function confirmDividendPayload(int $walletId, int $assetId, array $overrides = []): array
{
    return array_merge([
        'walletId' => $walletId,
        'assetId' => $assetId,
        'exDate' => '2026-02-01',
        'amount' => 50.0,
    ], $overrides);
}

it('encaisse le dividende et renvoie le visiteur', function () {
    $this->from('/')
        ->post('/dividendes', confirmDividendPayload($this->wallet->id, $this->instrument->id))
        ->assertRedirect('/');

    expect(Transaction::query()->where('type', 'dividend')->count())->toBe(1);
});

it('refuse proprement un double encaissement plutôt qu\'une 500 brute', function () {
    $this->post('/dividendes', confirmDividendPayload($this->wallet->id, $this->instrument->id))
        ->assertRedirect();

    /**
     * Le double clic ou le retour arrière rejoue le même détachement : `ConfirmDividend` lève un
     * `RuntimeException` nu, que le contrôleur doit traduire en erreur de session, pas en 500.
     */
    $this->post('/dividendes', confirmDividendPayload($this->wallet->id, $this->instrument->id))
        ->assertSessionHasErrors('exDate');

    expect(Transaction::query()->where('type', 'dividend')->count())->toBe(1);
});
