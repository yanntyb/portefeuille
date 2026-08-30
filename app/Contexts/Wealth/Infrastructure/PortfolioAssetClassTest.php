<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Infrastructure\PortfolioAssetClass;

it('describes itself from its exposure', function () {
    $commodity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Commodity]);

    expect($commodity->key())->toBe('commodity')
        ->and($commodity->label())->toBe('Matières premières')
        ->and($commodity->href())->toBe('/matieres-premieres')
        ->and($commodity->color())->toBe('commodity')
        ->and($commodity->incomeLabel())->toBeNull();
});

it('labels the income of an exposure that distributes', function () {
    $equity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Equity]);

    expect($equity->incomeLabel())->toBe('Dividendes');
});

it('renames the unsectorised share of an exposure after the exposure itself', function () {
    // Ni le bitcoin ni l'or n'ont de secteur boursier : leur valeur tombait dans « Autre »,
    // deuxième plus gros bloc du patrimoine, quand leur exposition la nomme déjà.
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $coin = Instrument::factory()->ofType(InstrumentType::Crypto)->create();
    Price::factory()->create(['asset_id' => $coin->id, 'date' => now(), 'close' => 1000]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $coin->id,
        'quantity' => 2,
        'avg_cost' => 800,
    ]);

    $slices = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Crypto])
        ->sectorSlicesFor($user->id);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->label)->toBe('Crypto')
        ->and($slices[0]->value)->toBe(2000.0);
});

it('leaves a sectorised slice under its own sector', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $stock = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $stock->id, 'date' => now(), 'close' => 100]);
    SectorAllocation::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Technology,
        'weight' => 1.0,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $stock->id,
        'quantity' => 3,
        'avg_cost' => 80,
    ]);

    $slices = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Equity])
        ->sectorSlicesFor($user->id);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->label)->toBe(Sector::Technology->getLabel());
});

it('counts the dividends received in the realized gain of a distributing exposure', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    // Dix titres détenus depuis le 1er janvier 2026, un détachement de 2 € par titre après.
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-02-01',
        'amount_per_share' => 2.0,
    ]);

    $snapshot = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Equity])
        ->snapshotFor($user->id);

    // Aucune vente dans le jeu : le réalisé vaut les 20 € encaissés. Le gain latent les rate,
    // dernier cours et prix payé étant tous deux bruts.
    expect($snapshot->realized)->toBe(20.0);
});

it('leaves the realized gain of a silent exposure to its sales alone', function () {
    ['user' => $user] = cryptoFixture();

    $snapshot = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Crypto])
        ->snapshotFor($user->id);

    expect($snapshot->realized)->toBe(0.0);
});
