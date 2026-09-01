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

    /** La règle d'or, lue sur les lignes elles-mêmes et non sur le total qu'elles alimentent. */
    $sum = round(array_sum(array_map(fn (AssetClassData $line): float => $line->invested, $overview->classes)), 2);

    expect($sum)->toBe($netContributions)
        ->and($overview->totalInvested)->toBe($netContributions);

    foreach ($overview->classes as $line) {
        /**
         * Aucune exposition ne part en investi négatif : c'est le plafonnement au coût qui le
         * garantit, un apport ne restant jamais imputé à des titres vendus. Les liquidités, elles,
         * le peuvent — capital repris au-delà de ce qui a été mis, ou plus-value réalisée puis
         * retirée —, et c'est le sens de la situation, pas une anomalie.
         */
        if ($line->key !== 'cash') {
            expect($line->invested)->toBeGreaterThanOrEqual(0.0);
        }
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
    /**
     * L'arbitrage d'une exposition contre une autre : `netContributions` laisse l'étiquette sur les
     * actions, mais le capital est passé en crypto. Sans la redistribution du reliquat, les
     * 1 000 € d'apport disparaissaient du total.
     */
    'arbitrage complet vers une autre exposition' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
            Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-06', 'close' => 100.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
                'date' => '2026-01-04', 'quantity' => 12, 'unit_price' => 100, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    /** Le même arbitrage à moitié : 600 € replacés, 600 € laissés en caisse. */
    'arbitrage partiel, le reste laissé en caisse' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
            Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-06', 'close' => 100.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
                'date' => '2026-01-04', 'quantity' => 6, 'unit_price' => 100, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    /**
     * La moins-value réalisée : 1 000 € apportés, 600 € en caisse. Sans le retrait de la borne au
     * solde, l'investi retombait à 600 et la perte disparaissait de l'écran.
     */
    'moins-value réalisée' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 60, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    /** La redistribution et la perte se croisent : 400 € replacés en crypto, la perte reste en caisse. */
    'moins-value puis rachat partiel dans une autre exposition' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
            Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-06', 'close' => 100.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 60, 'fees' => 0,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
                'date' => '2026-01-04', 'quantity' => 4, 'unit_price' => 100, 'fees' => 0,
            ]);
        },
        1000.0,
    ],
    /**
     * Le retrait du produit d'une vente : 1 000 € mis, 1 200 € repris, donc −200 € d'apports nets.
     * `netContributions()` n'en retranchait que la part d'apport prise en FIFO, et le tableau de
     * bord annonçait −1 000 € sur un porteur en réalité gagnant de 200 €.
     */
    'retrait du produit d\'une vente' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
            ]);
            Transaction::factory()->withdrawal()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-04', 'amount' => 1200,
            ]);
        },
        -200.0,
    ],
    /** Vente partielle puis retrait de son produit : la moitié des titres reste en portefeuille. */
    'vente partielle puis retrait de son produit' => [
        function (User $user, Wallet $wallet): void {
            $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
            Price::factory()->create(['asset_id' => $stock->id, 'date' => '2026-01-06', 'close' => 120.0]);
            Transaction::factory()->deposit()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
            ]);
            Transaction::factory()->buy()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
            ]);
            Transaction::factory()->sell()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
                'date' => '2026-01-03', 'quantity' => 5, 'unit_price' => 120, 'fees' => 0,
            ]);
            Transaction::factory()->withdrawal()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-04', 'amount' => 600,
            ]);
        },
        400.0,
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

/**
 * L'arbitrage d'une exposition contre une autre, sur ses chiffres et non sur le seul total :
 * l'apport passe entièrement aux actions vers la crypto, qui porte désormais le capital, et rien
 * ne dort en caisse.
 */
it('déplace l\'apport vers l\'exposition qui a repris le capital', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
    Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-06', 'close' => 100.0]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
        'date' => '2026-01-04', 'quantity' => 12, 'unit_price' => 100, 'fees' => 0,
    ]);

    $classes = collect(app(GetWealthOverview::class)($user->id)->classes)->keyBy('key');

    expect($classes['crypto']->invested)->toBe(1000.0)
        ->and($classes['equity']->invested)->toBe(0.0)
        ->and($classes['cash']->invested)->toBe(0.0)
        ->and($classes['crypto']->gain)->toBe(200.0);
});

/** Le même arbitrage à moitié : 600 € replacés en crypto, 600 € laissés dormir en caisse. */
it('ne déplace que la part du capital réellement replacée', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $coin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);
    Price::factory()->create(['asset_id' => $coin->id, 'date' => '2026-01-06', 'close' => 100.0]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $coin->id,
        'date' => '2026-01-04', 'quantity' => 6, 'unit_price' => 100, 'fees' => 0,
    ]);

    $classes = collect(app(GetWealthOverview::class)($user->id)->classes)->keyBy('key');

    expect($classes['crypto']->invested)->toBe(600.0)
        ->and($classes['equity']->invested)->toBe(0.0)
        ->and($classes['cash']->invested)->toBe(400.0)
        ->and($classes['cash']->value)->toBe(600.0)
        /** Les 200 € de plus-value dorment en caisse, sans qu'aucune classe n'invente de rendement. */
        ->and($classes['cash']->gain)->toBe(200.0);
});

/**
 * La moins-value réalisée s'affiche là où l'argent se trouve. La borne au solde annonçait
 * « Investi 600, Gain 0 € » pour 1 000 € sortis de la poche : 400 € apportés et perdus
 * s'évaporaient de l'écran.
 *
 * Ce n'est pas un double comptage avec le réalisé de l'exposition : `GetWealthOverview` calcule
 * `totalGain = totalValue − totalInvested` et porte le réalisé dans un champ séparé, jamais
 * additionné. Le cas gagnant en est le miroir exact, ratifié depuis la tâche 10.
 */
it('porte aux liquidités la moins-value réellement encaissée', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 60, 'fees' => 0,
    ]);

    $overview = app(GetWealthOverview::class)($user->id);
    $classes = collect($overview->classes)->keyBy('key');

    expect($classes['cash']->value)->toBe(600.0)
        ->and($classes['cash']->invested)->toBe(1000.0)
        ->and($classes['cash']->gain)->toBe(-400.0)
        ->and($classes['equity']->invested)->toBe(0.0)
        /** Le réalisé de l'exposition dit la même perte, dans un champ que le total n'additionne pas. */
        ->and($classes['equity']->realizedGain)->toBe(-400.0)
        ->and($overview->totalInvested)->toBe(1000.0)
        ->and($overview->totalValue)->toBe(600.0)
        ->and($overview->totalGain)->toBe(-400.0);
});

/**
 * Le cas que la borne au solde protégeait, et qui doit rester intact : un apport encore
 * entièrement immobilisé en titres ne doit pas afficher « Investi 1 000, Gain −1 000 € » sur une
 * caisse vide. C'est le plafonnement au coût qui s'en charge, pas une borne haute.
 */
it('ne met aucune perte aux liquidités tant que l\'apport est immobilisé en titres', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    Price::factory()->create(['asset_id' => $stock->id, 'date' => '2026-01-06', 'close' => 100.0]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);

    $classes = collect(app(GetWealthOverview::class)($user->id)->classes)->keyBy('key');

    expect($classes['cash']->value)->toBe(0.0)
        ->and($classes['cash']->invested)->toBe(0.0)
        ->and($classes['cash']->gain)->toBe(0.0)
        ->and($classes['equity']->invested)->toBe(1000.0);
});

/**
 * Retirer le produit d'une vente laisse le porteur gagnant : 1 000 € mis, 1 200 € repris. L'écran
 * annonçait −1 000 € — `netContributions()` ne retranchait d'un retrait que la part d'apport qu'il
 * consommait en FIFO, et un retrait payé par une vente ne diminuait donc rien.
 */
it('dit gagnant le porteur qui a repris plus qu\'il n\'a mis', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-03', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->withdrawal()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-04', 'amount' => 1200,
    ]);

    $overview = app(GetWealthOverview::class)($user->id);
    $classes = collect($overview->classes)->keyBy('key');

    expect($classes['cash']->value)->toBe(0.0)
        ->and($classes['cash']->invested)->toBe(-200.0)
        ->and($classes['cash']->gain)->toBe(200.0)
        ->and($overview->totalInvested)->toBe(-200.0)
        ->and($overview->totalValue)->toBe(0.0)
        ->and($overview->totalGain)->toBe(200.0);
});

/**
 * Le scénario qui a motivé de lire le coût sur les lignes plutôt que sur `totalCost` : un actif
 * sans aucun cours — ajouté avant la première synchronisation, ou ticker délisté. Son apport était
 * compté, sa contrepartie non, et l'écran inventait une perte du montant de l'achat.
 */
it('n\'invente aucune perte sur un actif dont aucun cours n\'est connu', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $stock->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);

    $overview = app(GetWealthOverview::class)($user->id);
    $classes = collect($overview->classes)->keyBy('key');

    /** Les titres valent ce qu'ils ont coûté, la caisse est vide, et personne ne perd rien. */
    expect($classes['equity']->value)->toBe(1000.0)
        ->and($classes['equity']->invested)->toBe(1000.0)
        ->and($classes['equity']->gain)->toBe(0.0)
        ->and($classes['cash']->invested)->toBe(0.0)
        ->and($classes['cash']->gain)->toBe(0.0)
        ->and($overview->totalInvested)->toBe(1000.0)
        ->and($overview->totalValue)->toBe(1000.0)
        ->and($overview->totalGain)->toBe(0.0);
});
