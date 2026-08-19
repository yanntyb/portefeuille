<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Un utilisateur détenant 10 titres depuis 2024, deux détachements : un dans les douze derniers
 * mois, un plus ancien.
 *
 * @return array{user: User, instrument: Instrument}
 */
function dividendFixture(): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2024-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);

    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    return ['user' => $user, 'instrument' => $instrument];
}

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
