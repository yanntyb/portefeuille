<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
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

    expect($summary->totalValue)->toBe(0.0)
        ->and($summary->totalCost)->toBe(0.0)
        ->and($summary->totalGain)->toBe(0.0)
        ->and($summary->totalGainPct)->toBe(0.0)
        ->and($summary->holdings)->toBe([]);
});

/**
 * Les treize clés sont recopiées à la main depuis `Portfolio\Datas\HoldingLineData::jsonSerialize()` :
 * les tirer de la classe voisine ne prouverait rien. `typeLabel` et `assetClassLabel` n'ont pas de
 * propriété — elles se dérivent de l'enum, et c'est précisément ce que ce test protège.
 */
it('rend les treize clés que le tableau du front attend, dans l\'ordre', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $line = $this->overview->overviewFor($user->id, AssetClass::Equity)->holdings[0];

    expect($line->jsonSerialize())->toBe([
        'assetId' => $instrument->id,
        'assetName' => 'ACME',
        'ticker' => 'ACM',
        'type' => InstrumentType::Stock->value,
        'typeLabel' => InstrumentType::Stock->getLabel(),
        'assetClass' => AssetClass::Equity->value,
        'assetClassLabel' => AssetClass::Equity->getLabel(),
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
