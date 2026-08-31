<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\Portfolio\Enums\AccountType;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
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

it('ajoute le dividende encaissé au gain réalisé, au total comme à la position', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    /** Dix titres détenus depuis le 1er janvier 2026, un détachement de 2 € par titre après. */
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-02-01',
        'amount_per_share' => 2.0,
    ]);

    $summary = $this->overview->overviewFor($user->id, AssetClass::Equity);
    $position = $this->overview->positionFor($user->id, $instrument->id);

    /**
     * Aucune vente dans le jeu : le réalisé vaut donc les 20 € encaissés, ni plus ni moins. Le
     * gain latent les rate — dernier cours et prix payé sont bruts — et rien d'autre ne les
     * ramenait dans le patrimoine.
     */
    expect($summary->totalRealizedGain)->toBe(20.0)
        ->and($position->realizedGain)->toBe(20.0);
});

it('laisse le gain réalisé d\'une exposition muette aux seules cessions', function () {
    ['user' => $user] = cryptoFixture();

    expect($this->overview->overviewFor($user->id, AssetClass::Crypto)->totalRealizedGain)->toBe(0.0);
});
