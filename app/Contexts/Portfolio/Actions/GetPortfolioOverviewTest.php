<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

function makeHolding(User $user, InstrumentType $type, float $close, float $qty, float $avgCost): Instrument
{
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $qty,
        'avg_cost' => $avgCost,
    ]);

    return $asset;
}

it('computes value, cost and gain for a single holding', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->totalCost)->toBe(800.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->totalGainPct)->toBe(25.0)
        ->and($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->marketValue)->toBe(1000.0)
        ->and($overview->holdings[0]->gainPct)->toBe(25.0);
});

/**
 * Une position sans cours connu compte pour son prix de revient, et non pour rien : l'écarter du
 * total faisait peser zéro un actif ajouté avant la première synchronisation des cours, ou un
 * ticker délisté. Son gain reste nul, seul honnête faute de cotation.
 */
it('counts a holding without a known price at its cost basis', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80); // valued
    $wallet = Wallet::factory()->for($user)->create();
    $noPrice = Instrument::factory()->ofType(InstrumentType::Bond)->create();
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $noPrice->id,
        'quantity' => 5,
        'avg_cost' => 90,
    ]);

    $overview = app(GetPortfolioOverview::class)($user);

    /** 1 000 € valorisés au cours, plus 5 × 90 € au prix de revient. */
    expect($overview->totalValue)->toBe(1450.0)
        ->and($overview->totalCost)->toBe(1250.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->holdings)->toHaveCount(2);

    $bondLine = collect($overview->holdings)->firstWhere('type', InstrumentType::Bond);
    expect($bondLine->lastPrice)->toBeNull()
        ->and($bondLine->marketValue)->toBe(450.0)
        ->and($bondLine->gain)->toBe(0.0);
});

it('returns an empty overview when the user has no holdings', function () {
    $user = User::factory()->create();

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([]);
});

it('keeps market value but excludes gain when avg_cost is unknown', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80); // value 1000, cost 800

    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 4,
        'avg_cost' => null,
    ]);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1200.0)
        ->and($overview->totalCost)->toBe(800.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->totalGainPct)->toBe(25.0);

    $etfLine = collect($overview->holdings)->firstWhere('type', InstrumentType::ETF);
    expect($etfLine->marketValue)->toBe(200.0)
        ->and($etfLine->gain)->toBeNull()
        ->and($etfLine->gainPct)->toBeNull();
});

it('keeps only the holdings of the requested classes', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 6, avgCost: 50);   // 600
    makeHolding($user, InstrumentType::Crypto, close: 100, qty: 4, avgCost: 50);  // 400

    $securities = app(GetPortfolioOverview::class)($user, HoldingScope::ofClasses([AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity]));
    $crypto = app(GetPortfolioOverview::class)($user, HoldingScope::ofClasses([AssetClass::Crypto]));

    expect($securities->totalValue)->toBe(600.0)
        ->and($securities->holdings)->toHaveCount(1)
        ->and($securities->holdings[0]->type)->toBe(InstrumentType::Stock)
        ->and($crypto->totalValue)->toBe(400.0)
        ->and($crypto->holdings)->toHaveCount(1)
        ->and($crypto->holdings[0]->type)->toBe(InstrumentType::Crypto);
});

it('keeps only the holdings of the exposures it is given', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Price::factory()->create(['asset_id' => $stock->id, 'close' => 50.0]);

    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 2, 'avg_cost' => 80.0,
    ]);
    Holding::factory()->create([
        'asset_id' => $stock->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 4, 'avg_cost' => 40.0,
    ]);

    $overview = app(GetPortfolioOverview::class)($user, HoldingScope::ofClasses([AssetClass::Commodity]));

    expect($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->assetId)->toBe($gold->id)
        ->and($overview->holdings[0]->assetClass)->toBe(AssetClass::Commodity)
        ->and($overview->totalValue)->toBe(200.0);
});

it('reads the holdings once, however many exposures ask for them', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $instrument = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    Price::factory()->create(['asset_id' => $instrument->id, 'close' => 10.0]);
    Holding::factory()->create([
        'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 5.0,
    ]);

    $overview = app(GetPortfolioOverview::class);
    $overview($user, HoldingScope::ofClasses([AssetClass::Equity]));

    DB::enableQueryLog();

    foreach (AssetClass::cases() as $class) {
        $overview($user, HoldingScope::ofClasses([$class]));
    }

    expect(DB::getQueryLog())->toBeEmpty();
});

it('is bound scoped so every resolution within a request shares the same memoised instance', function () {
    expect(app(GetPortfolioOverview::class))->toBe(app(GetPortfolioOverview::class));
});

it('porte l\'enveloppe de détention sur chaque ligne', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $line = app(GetPortfolioOverview::class)($user)->holdings[0];

    expect($line->walletId)->toBe($wallet->id)
        ->and($line->walletName)->toBe('PEA')
        ->and($line->accountType)->toBe(AccountType::Pea)
        ->and($line->jsonSerialize()['accountTypeLabel'])->toBe('PEA');
});

it('rend deux lignes distinctes pour un même actif tenu dans deux enveloppes', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);

    foreach ([$pea, $cto] as $wallet) {
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 5,
            'avg_cost' => 80,
        ]);
    }

    $holdings = app(GetPortfolioOverview::class)($user)->holdings;

    expect($holdings)->toHaveCount(2)
        ->and(array_map(fn ($line): int => $line->walletId, $holdings))
        ->toEqualCanonicalizing([$pea->id, $cto->id]);
});

/**
 * `totalRealizedGain` se lit sur `GetRealizedGains`, qui ne connaît que les ventes : un
 * détachement théorique (`Market\Dividend`, dérivé des positions détenues à l'ex-date) ne doit
 * jamais s'y ajouter, même quand la position qu'il a effleurée a depuis été entièrement soldée et
 * n'a donc plus de ligne `Holding` pour le porter.
 */
it('ignore le détachement théorique dans le gain réalisé, même sur une position entièrement soldée', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($user)->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    /** Dix titres détenus à cette date : un détachement théorique de 20 € (2 € × 10) à ignorer. */
    Dividend::factory()->create([
        'asset_id' => $asset->id,
        'ex_date' => '2026-01-20',
        'amount_per_share' => 2.0,
    ]);

    /** 200 € de plus-value de cession, et rien de plus, alors que la position n'existe plus. */
    expect(app(GetPortfolioOverview::class)($user, HoldingScope::ofClasses([AssetClass::Equity]))->totalRealizedGain)->toBe(200.0);
});

/**
 * `netContributions` porte ce que le porteur a réellement sorti de sa poche
 * (`CashLedger::netContributions()`), et non le coût des titres : un rachat financé par une vente
 * ne crée aucun apport nouveau, quand `totalCost` l'aurait compté une seconde fois.
 */
it('mesure l\'investi aux apports nets, pas au coût des titres', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($user)->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-03-01', 'quantity' => 10, 'unit_price' => 110, 'fees' => 0,
    ]);

    /**
     * 1 000 € sortis de la poche, une seule fois : le rachat est financé par la vente, il ne
     * crée aucun apport. L'ancien « coût des titres » aurait dit 1 100 €.
     */
    expect(app(GetPortfolioOverview::class)($user, HoldingScope::all())->netContributions)->toBe(1000.0);
});
