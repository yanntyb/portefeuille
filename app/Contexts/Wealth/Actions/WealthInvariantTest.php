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

/**
 * L'invariant du chantier des liquidités : la somme des investis de toutes les classes fait
 * exactement les apports nets — ce que le porteur a réellement sorti de sa poche —, et aucune
 * classe ne part en gain négatif parce que le capital serait resté imputé à une exposition vendue.
 *
 * Chaque cas rejoue une histoire complète en base plutôt que d'appeler la formule : c'est le
 * câblage `PortfolioAssetClass` + `CashClass` qui est en jeu, l'un déclarant `totalCost` avait
 * suffi à rendre l'aller-retour faux.
 */
it('répartit les apports nets entre les classes sans en perdre ni en inventer', function (callable $history, float $netContributions) {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);

    $history($user, $wallet);

    $overview = app(GetWealthOverview::class)($user->id);

    expect($overview->totalInvested)->toBe($netContributions);

    foreach ($overview->classes as $line) {
        expect($line->invested)->toBeGreaterThanOrEqual(0.0);
    }
})->with([
    'apport 1 000, achat 1 000' => [
        function (User $user, Wallet $wallet): void {
            $asset = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-05', 'close' => 100.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    'vente 1 200, rien racheté' => [
        function (User $user, Wallet $wallet): void {
            $asset = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-05', 'close' => 120.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    'produit de la vente réemployé dans la même exposition' => [
        function (User $user, Wallet $wallet): void {
            $sold = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            $bought = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Price::factory()->create(['asset_id' => $bought->id, 'date' => '2026-01-05', 'close' => 100.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $sold->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $sold->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $bought->id,
                'date' => '2026-01-04', 'quantity' => 12, 'unit_price' => 100, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    'deux expositions financées séparément' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
            Price::factory()->create(['asset_id' => $stock->id, 'date' => '2026-01-05', 'close' => 100.0]);
            Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-05', 'close' => 50.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1500,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 50, 'fees' => 0,
            ]);
        },
        1500.0,
    ],
]);

/**
 * Le cas que `totalCost` rendait faux : un aller-retour réemployé affichait « Investi 1 200,
 * Gain 0 € » là où le porteur n'a sorti que 1 000 € et gagné 200 €.
 */
it('ne recompte pas l\'apport quand le produit d\'une vente est réemployé', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $sold = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $bought = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    Price::factory()->create(['asset_id' => $bought->id, 'date' => '2026-01-05', 'close' => 100.0]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $sold->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $sold->id,
        'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $bought->id,
        'date' => '2026-01-04', 'quantity' => 12, 'unit_price' => 100, 'fees' => 0,
    ]);

    $overview = app(GetWealthOverview::class)($user->id);
    $equity = collect($overview->classes)->firstWhere('key', 'equity');

    expect($equity->invested)->toBe(1000.0)
        ->and($equity->value)->toBe(1200.0)
        ->and($equity->gain)->toBe(200.0);
});
