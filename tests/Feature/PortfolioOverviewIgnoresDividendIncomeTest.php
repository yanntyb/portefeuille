<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * La spec des dividendes encaissés promet qu'aucun chiffre déjà affiché ne change de valeur : ni
 * le coût de revient, ni le gain. C'est vrai aujourd'hui par construction — `GetPortfolioOverview`
 * ne lit jamais la table des dividendes —, mais rien ne le garde. Ce test tombera le jour où
 * quelqu'un « améliorera » le gain en y mêlant le revenu perçu.
 */
it('rend un gain et un coût de revient identiques avec ou sans détachement en base', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->ofType(InstrumentType::Stock)->create();

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'unit_price' => 80,
        'date' => now()->subYears(2),
    ]);

    $withoutDividends = app(GetPortfolioOverview::class)($user);

    // Un montant volontairement élevé (50 € au total) : s'il fuitait dans le gain ou le coût, la
    // différence serait large, pas noyée dans un arrondi.
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => now()->subMonths(6)->format('Y-m-d'),
        'amount_per_share' => 5.0,
    ]);

    $withDividends = app(GetPortfolioOverview::class)($user);

    expect($withDividends->totalCost)->toBe($withoutDividends->totalCost)
        ->and($withDividends->totalGain)->toBe($withoutDividends->totalGain)
        ->and($withDividends->totalGainPct)->toBe($withoutDividends->totalGainPct)
        ->and($withDividends->holdings[0]->avgCost)->toBe($withoutDividends->holdings[0]->avgCost)
        ->and($withDividends->holdings[0]->gain)->toBe($withoutDividends->holdings[0]->gain);
});
