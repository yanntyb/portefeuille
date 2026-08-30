<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

it('porte la page liste et une fiche par position détenue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['classes']['equity'])->toHaveKeys([
        'overview', 'trends', 'evolutionSeries', 'performances', 'classAnalysis', 'sectorBreakdown',
    ])
        ->and($snapshot['assets'])->toHaveKey($instrument->id)
        ->and($snapshot['assets'][$instrument->id])->toHaveKeys([
            'instrument', 'performances', 'priceHistory', 'valuation', 'dividends',
        ]);
});

/**
 * `pagesFor()` appelle `GetInstrumentDetail` (donc `PortfolioOverviewPort::positionFor()`) et
 * `Income\...\PortfolioPositionHistory::positionFor()` une fois par position détenue. Sans la
 * mémoïsation de `GetPortfolioPositions` par utilisateur, chacun de ces appels relirait tout le
 * portefeuille et tous les derniers cours de l'utilisateur — un nombre de requêtes qui grossirait
 * avec le nombre de positions plutôt que de rester fixe.
 *
 * Le nombre de requêtes vers `holdings_projection` doit rester à 3 quel que soit le nombre de
 * positions détenues : une par lecteur qui interroge ce projecteur une fois par instantané —
 * `GetPortfolioPositions` (mémoïsée, cette task), `GetPortfolioOverview` et `GetSectorBreakdown`
 * (déjà mémoïsées chacune de leur côté). Le jeu en sème une douzaine (`cryptoFixture()` plus dix
 * positions supplémentaires) pour que 3 prouve l'indépendance au nombre de positions, et non un
 * chiffre qui se serait simplement trouvé correct pour deux. Sans la mémoïsation de
 * `GetPortfolioPositions`, ce total grossirait avec N (vérifié en désactivant temporairement `??=`
 * pendant l'écriture de ce test) : la régression corrigée par cette task est bien détectée.
 */
it('ne relit pas les positions une fois par fiche construite', function () {
    ['user' => $user] = cryptoFixture();

    $wallet = Wallet::factory()->for($user)->create();

    foreach (range(1, 10) as $i) {
        $instrument = Instrument::factory()->create(['ticker' => "TST{$i}"]);
        Price::factory()->create(['asset_id' => $instrument->id, 'close' => 100.0]);
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $instrument->id,
            'quantity' => 1,
            'avg_cost' => 80.0,
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    app(BuildMarketViewSnapshot::class)($user->id);

    $holdingsQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], '"holdings_projection"'))
        ->count();

    expect($holdingsQueries)->toBe(3);
});

it('range la crypto à part, sans dividendes sur ses fiches', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['assets'])->toHaveKey($bitcoin->id)
        ->and($snapshot['assets'][$bitcoin->id])->not->toHaveKey('dividends');
});

it('carries one list per exposure and every held asset once', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 80.0,
    ]);

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect(array_keys($snapshot['classes']))->toBe(AssetClass::values())
        ->and($snapshot['assets'])->toHaveKey($gold->id);
});

it('withholds sectors from the exposures that have none', function () {
    $user = User::factory()->create();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['classes']['equity'])->toHaveKeys(['performances', 'sectorBreakdown'])
        ->and($snapshot['classes']['crypto'])->not->toHaveKey('sectorBreakdown');
});

/**
 * Fige la composition servie quand la base n'a aucun utilisateur : c'est elle que garantissait
 * `emptyClasses()`, et les ports doivent la reproduire sans ce chemin dédié.
 */
it('rend les mêmes listes vides sans aucun utilisateur', function () {
    $snapshot = app(BuildMarketViewSnapshot::class)(0);

    expect($snapshot['assets'])->toBe([]);
    expect(array_keys($snapshot['classes']))
        ->toBe(array_map(fn (AssetClass $class): string => $class->value, AssetClass::cases()));

    $equity = $snapshot['classes']['equity'];

    expect(array_keys($equity))
        ->toBe(['overview', 'trends', 'evolutionSeries', 'performances', 'classAnalysis', 'transactions', 'sectorBreakdown']);
    // gainPct est nul, et non zéro, sur un coût nul : « 0 % » mentirait sur une mise inconnue.
    expect($equity['overview']->jsonSerialize())
        ->toBe([
            'totalValue' => 0.0,
            'totalCost' => 0.0,
            'totalGain' => 0.0,
            'totalGainPct' => null,
            'totalRealizedGain' => 0.0,
            'holdings' => [],
        ]);
    expect($equity['trends'])->toBe([]);
    expect($equity['evolutionSeries']->jsonSerialize())->toBe(['labels' => [], 'perAsset' => []]);
    expect($equity['performances'])->toBe([])
        ->and($equity['transactions'])->toBe([])
        ->and($equity['sectorBreakdown'])->toBe([]);

    expect(array_keys($snapshot['classes']['crypto']))
        ->toBe(['overview', 'trends', 'evolutionSeries', 'performances', 'classAnalysis', 'transactions']);
});
