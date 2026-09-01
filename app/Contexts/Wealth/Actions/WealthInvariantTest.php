<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Datas\AssetClassData;

it('totals the wealth as the exact sum of its classes', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    foreach ([InstrumentType::Stock, InstrumentType::Commodity, InstrumentType::Crypto] as $type) {
        $instrument = Instrument::factory()->create(['type' => $type]);
        Price::factory()->create(['asset_id' => $instrument->id, 'close' => 100.0]);
        Holding::factory()->create([
            'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
            'user_id' => $user->id, 'quantity' => 3, 'avg_cost' => 60.0,
        ]);
    }

    $overview = app(GetWealthOverview::class)($user->id);

    $sum = array_sum(array_map(fn (AssetClassData $line): float => $line->value, $overview->classes));

    // Trois positions de 3 titres à 100 € : le total est connu d'avance, et le comparer à la
    // somme des lignes ne suffirait pas si les deux dérivaient ensemble.
    expect($overview->totalValue)->toBe(900.0)
        ->and(round($sum, 2))->toBe($overview->totalValue);
});

/**
 * Le revenu du patrimoine se filtre par origine et non par exposition : deux expositions
 * partageant une origine compteraient deux fois les mêmes encaissements.
 */
it('ne recompte pas aux liquidités le dividende que l\'exposition a déjà déclaré', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    Price::factory()->create(['asset_id' => $instrument->id, 'close' => 100.0]);
    Holding::factory()->create([
        'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 100.0,
    ]);
    Transaction::factory()->dividend()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => now()->subDay(), 'amount' => 50,
    ]);

    $income = app(GetWealthIncome::class)($user->id);
    $dividends = app(GetIncomeSummary::class)($user->id, IncomeSource::Dividend);

    /**
     * Les 50 € dorment désormais en caisse, mais `CashClass::incomeLabel()` rend `null` : ils ne
     * doivent apparaître qu'une fois, sous « Dividendes ».
     */
    expect($income->origins)->toHaveCount(1)
        ->and($income->origins[0]->label)->toBe('Dividendes')
        ->and($income->monthlyTotal)->toBe(round($dividends->last12Months / 12, 2));
});
