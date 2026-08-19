<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('totalise le perçu, les douze derniers mois et la ventilation par source', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $summary = app(GetIncomeSummary::class)($user->id);

    expect($summary->totalReceived)->toBe(13.0)
        ->and($summary->last12Months)->toBe(8.0)
        ->and($summary->bySource)->toBe(['dividend' => 13.0]);
});

it('rend un résumé vide sans aucun revenu', function () {
    $user = User::factory()->create();

    $summary = app(GetIncomeSummary::class)($user->id);

    expect($summary->totalReceived)->toBe(0.0)
        ->and($summary->last12Months)->toBe(0.0)
        ->and($summary->bySource)->toBe([]);
});

it('ne compte pas le revenu d\'un autre utilisateur', function () {
    $this->travelTo('2026-08-19 10:00:00');
    dividendFixture();
    $other = User::factory()->create();

    expect(app(GetIncomeSummary::class)($other->id)->totalReceived)->toBe(0.0);
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

    expect(app(GetIncomeSummary::class)($user->id)->last12Months)->toBe(5.0);
});
