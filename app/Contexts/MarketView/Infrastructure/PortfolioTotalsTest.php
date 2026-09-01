<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\MarketView\Infrastructure\PortfolioTotals;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->overview = app(PortfolioOverviewPort::class);
});

it('rend le total et les lignes d\'une seule exposition', function () {
    ['user' => $user] = portfolioFixture();

    $summary = $this->overview->overviewFor($user->id, AssetClass::Equity);

    expect($summary->totalValue)->toBe(1000.0)
        ->and($summary->totalCost)->toBe(800.0)
        ->and($summary->totalGain)->toBe(200.0)
        ->and($summary->holdings)->toHaveCount(1)
        ->and($summary->holdings[0]->assetName)->toBe('ACME');
});

/**
 * `PortfolioSummaryData` est la jumelle de `Portfolio\PortfolioOverviewData` : `cash` doit s'y
 * retrouver à l'identique, sans quoi la page d'exposition ne peut jamais annoncer ce qui reste à
 * replacer. Le cash n'est pas ventilé par exposition — il vaut donc le même montant, quelle que
 * soit l'exposition demandée.
 *
 * `portfolioFixture()` pose déjà un achat de 800 € (10 titres à 80 €) : le versement de 1 500 €
 * laisse donc 700 € non replacés.
 */
it('reporte le cash de l\'utilisateur, non ventilé par exposition', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'date' => '2026-01-01',
        'amount' => 1500,
        'auto' => false,
    ]);

    $equity = $this->overview->overviewFor($user->id, AssetClass::Equity);
    $crypto = $this->overview->overviewFor($user->id, AssetClass::Crypto);

    expect($equity->cash)->toBe(700.0)
        ->and($crypto->cash)->toBe(700.0);
});

it('ne montre pas les actifs des autres expositions', function () {
    ['user' => $user] = cryptoFixture();

    $equity = $this->overview->overviewFor($user->id, AssetClass::Equity);
    $crypto = $this->overview->overviewFor($user->id, AssetClass::Crypto);

    expect($equity->holdings)->toHaveCount(1)
        ->and($equity->holdings[0]->assetClass)->toBe(AssetClass::Equity)
        ->and($crypto->holdings)->toHaveCount(1)
        ->and($crypto->holdings[0]->assetName)->toBe('Bitcoin');
});

it('rend un total vide pour un utilisateur inconnu', function () {
    $summary = $this->overview->overviewFor(999, AssetClass::Equity);

    // gainPct est nul, et non zéro, sur un coût nul : « 0 % » mentirait sur une mise inconnue.
    expect($summary->totalValue)->toBe(0.0)
        ->and($summary->totalCost)->toBe(0.0)
        ->and($summary->totalGain)->toBe(0.0)
        ->and($summary->totalGainPct)->toBeNull()
        ->and($summary->holdings)->toBe([]);
});

/**
 * Les dix-sept clés sont recopiées à la main depuis `Portfolio\Datas\HoldingLineData::jsonSerialize()` :
 * les tirer de la classe voisine ne prouverait rien. `typeLabel`, `assetClassLabel` et
 * `accountTypeLabel` n'ont pas de propriété — elles se dérivent de l'enum, et c'est précisément ce
 * que ce test protège.
 */
it('rend les dix-sept clés que le tableau du front attend, dans l\'ordre', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $line = $this->overview->overviewFor($user->id, AssetClass::Equity)->holdings[0];

    expect($line->jsonSerialize())->toBe([
        'assetId' => $instrument->id,
        'assetName' => 'ACME',
        'ticker' => 'ACM',
        'type' => InstrumentType::Stock->value,
        'typeLabel' => InstrumentType::Stock->getLabel(),
        'assetClass' => AssetClass::Equity->value,
        'assetClassLabel' => AssetClass::Equity->getLabel(),
        'walletId' => $wallet->id,
        'walletName' => 'Compte-titres',
        'accountType' => AccountType::Cto->value,
        'accountTypeLabel' => AccountType::Cto->getLabel(),
        'quantity' => 10.0,
        'avgCost' => 80.0,
        'lastPrice' => 100.0,
        'marketValue' => 1000.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
    ]);
});

/**
 * L'action voisine est liée en `scoped` et mémoïse ses lignes par utilisateur : une exposition de
 * plus ne doit pas relire le portefeuille. L'adaptateur doit donc l'injecter, jamais la construire.
 */
it('partage la lecture mémoïsée du portefeuille entre deux expositions', function () {
    ['user' => $user] = cryptoFixture();
    $this->overview->overviewFor($user->id, AssetClass::Equity);

    DB::enableQueryLog();
    $this->overview->overviewFor($user->id, AssetClass::Crypto);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(fn (string $query): bool => str_contains($query, '"holdings"')))->toBeEmpty();
});

/**
 * Le détachement (`Market\Dividend`, théorique, dérivé des positions détenues à l'ex-date) n'entre
 * dans aucun solde : seule une transaction de dividende réellement saisie alimente le cash. Compter
 * ce détachement en plus du gain réalisé le compterait donc en pure perte, sans qu'aucun mouvement
 * d'espèces n'y corresponde.
 */
it('ne compte pas le détachement théorique dans le gain réalisé, au total comme à la position', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    /** Dix titres détenus depuis le 1er janvier 2026, un détachement de 2 € par titre après. */
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-02-01',
        'amount_per_share' => 2.0,
    ]);

    $summary = $this->overview->overviewFor($user->id, AssetClass::Equity);
    $position = $this->overview->positionFor($user->id, $instrument->id);

    /** Aucune vente dans le jeu : le réalisé est donc nul, le détachement n'étant plus additionné. */
    expect($summary->totalRealizedGain)->toBe(0.0)
        ->and($position->realizedGain)->toBe(0.0);
});

it('laisse le gain réalisé d\'une exposition muette aux seules cessions', function () {
    ['user' => $user] = cryptoFixture();

    expect($this->overview->overviewFor($user->id, AssetClass::Crypto)->totalRealizedGain)->toBe(0.0);
});

/**
 * Douze achetés, dix vendus : deux restent détenus, faute de quoi la vente soldant tout aurait
 * effacé la ligne `Holding` et `positionFor()` n'aurait plus rien à rendre — `GetPortfolioPositions`
 * ne connaît que les positions encore ouvertes, contrairement à `GetRealizedGains` qui lit les
 * ventes elles-mêmes.
 *
 * Le détachement `Market\Dividend` est le vrai piège : sans lui, `IncomePort::assetHistoryFor()`
 * rend un `totalReceived` nul qu'on ajoute ou non — le test passerait même si l'addition
 * revenait. La transaction de dividende, elle, ne nourrit jamais `IncomePort` (qui ignore les
 * transactions) ; elle est là pour montrer que le cash et le gain réalisé restent deux choses
 * distinctes.
 */
it('ne compte plus les dividendes en supplément du gain réalisé', function () {
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 12, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->dividend()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-15', 'amount' => 50,
    ]);
    /** Douze titres détenus à cette date : un détachement théorique de 24 € (2 € × 12) à ignorer. */
    Dividend::factory()->create([
        'asset_id' => $asset->id,
        'ex_date' => '2026-01-20',
        'amount_per_share' => 2.0,
    ]);

    /** 200 € de plus-value, et rien de plus : le dividende est entré par le compte espèces. */
    expect(app(PortfolioTotals::class)->positionFor($this->user->id, $asset->id)->realizedGain)->toBe(200.0);
});

/**
 * `overviewFor()` porte le gain réalisé d'un actif entièrement soldé — sa raison d'être, puisque
 * `GetRealizedGains` lit les ventes elles-mêmes et non une ligne `Holding` qui n'existe plus.
 * Un détachement théorique sur la période de détention ne doit pas non plus s'y ajouter.
 */
it('ignore le détachement théorique sur une position entièrement soldée', function () {
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    /** Dix titres détenus à cette date : un détachement théorique de 20 € (2 € × 10) à ignorer. */
    Dividend::factory()->create([
        'asset_id' => $asset->id,
        'ex_date' => '2026-01-20',
        'amount_per_share' => 2.0,
    ]);

    /** 200 € de plus-value de cession, et rien de plus, alors que la position n'existe plus. */
    expect($this->overview->overviewFor($this->user->id, AssetClass::Equity)->totalRealizedGain)->toBe(200.0);
});

it('mesure l\'investi aux apports nets, pas au coût des titres', function () {
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-03-01', 'quantity' => 10, 'unit_price' => 110, 'fees' => 0,
    ]);

    /**
     * 1 000 € sortis de la poche, une seule fois : le rachat est financé par la vente, il ne
     * crée aucun apport. L'ancien « coût des titres » aurait dit 1 100 €.
     */
    expect(app(GetPortfolioOverview::class)($this->user, null)->netContributions)->toBe(1000.0);
});
