<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * 10 titres à 80 € payés en 2024, deux détachements dont un dans les douze derniers mois.
 *
 * @return array{user: User, instrument: Instrument}
 */
function heldWithDividends(): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    /**
     * La position n'est pas insérée à la main : `TransactionObserver` la projette depuis cet achat
     * via `ProjectHolding`, qui fait un `updateOrCreate` sur `(asset_id, wallet_id)`. Insérer un
     * `Holding` après la transaction violerait cette clé primaire composite — 10 titres à 80 €
     * projettent exactement `quantity = 10, avg_cost = 80`.
     */
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2024-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);

    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    return ['user' => $user, 'instrument' => $instrument];
}

it('détaille les détachements perçus, du plus récent au plus ancien', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->receipts)->toHaveCount(2)
        ->and($history->receipts[0]->exDate)->toBe('2026-03-05')
        ->and($history->receipts[0]->quantity)->toBe(10.0)
        ->and($history->receipts[0]->amount)->toBe(8.0)
        ->and($history->totalReceived)->toBe(13.0)
        ->and($history->last12Months)->toBe(8.0);
});

it('rapporte le perçu de douze mois au coût de la position', function () {
    // 8 € perçus sur un coût de 800 € : 1 %.
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    expect(app(GetAssetDividendHistory::class)($user->id, $instrument->id)->yieldOnCost)->toBe(1.0);
});

it('laisse le rendement nul quand la position est soldée', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();
    Holding::query()->where('asset_id', $instrument->id)->delete();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->yieldOnCost)->toBeNull()
        ->and($history->totalReceived)->toBe(13.0);
});

it('rend un historique vide sur un instrument capitalisant', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->receipts)->toBe([])
        ->and($history->totalReceived)->toBe(0.0)
        ->and($history->yieldOnCost)->toBeNull();
});

it('ignore les détachements des autres actifs', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = heldWithDividends();
    $other = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $other->id, 'ex_date' => '2026-04-02', 'amount_per_share' => 9.0]);

    expect(app(GetAssetDividendHistory::class)($user->id, $other->id)->receipts)->toBe([]);
});

it('compte un détachement tombant exactement un an avant aujourd\'hui, quelle que soit l\'heure', function () {
    // L'heure d'exécution (22h ici) ne doit rien changer : la borne des douze mois est fixée à
    // minuit, pas à l'instant présent.
    $this->travelTo('2026-08-19 22:00:00');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2024-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2025-08-19', 'amount_per_share' => 0.5]);

    expect(app(GetAssetDividendHistory::class)($user->id, $instrument->id)->last12Months)->toBe(5.0);
});

it('se sérialise pour la page', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    $payload = json_decode(json_encode(app(GetAssetDividendHistory::class)($user->id, $instrument->id)), true);

    // `toEqual` et non `toBe` sur les montants : `json_encode()` sérialise un flottant à fraction
    // nulle sans son « .0 », que `json_decode` redonne en entier. La valeur est ce qui compte —
    // JavaScript n'a de toute façon qu'un seul type numérique.
    expect($payload['receipts'][0]['exDate'])->toBe('2026-03-05')
        ->and($payload['receipts'][0]['amount'])->toEqual(8.0)
        ->and($payload['totalReceived'])->toEqual(13.0)
        ->and($payload['yieldOnCost'])->toEqual(1.0);
});
