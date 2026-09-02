<?php

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioAnalysis;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('regroupe les enveloppes d\'un actif avant de mesurer la concentration', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    $analysis = app(GetPortfolioAnalysis::class)($user, HoldingScope::ofClasses([AssetClass::Equity]));

    /** Deux enveloppes, un seul actif : la concentration est totale, pas partagée en deux. */
    expect($analysis->concentration->top1)->toBe(100.0)
        ->and($analysis->concentration->hhi)->toBe(1.0)
        ->and($analysis->contributions)->toHaveCount(1);
});

it('nomme chaque contribution et la rapporte à la valeur totale', function () {
    ['user' => $user] = portfolioFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, HoldingScope::ofClasses([AssetClass::Equity]));

    expect($analysis->contributions[0]->assetName)->toBe('ACME')
        ->and($analysis->contributions[0]->weight)->toBe(100.0)
        ->and($analysis->contributions[0]->contribution)->toBe(20.0);
});

it('ne retient que l\'exposition demandée', function () {
    ['user' => $user] = cryptoFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, HoldingScope::ofClasses([AssetClass::Crypto]));

    expect($analysis->contributions)->toHaveCount(1)
        ->and($analysis->contributions[0]->assetName)->toBe('Bitcoin');
});

it('ne mesure rien sur un portefeuille vide', function () {
    ['user' => $user] = portfolioFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, HoldingScope::ofClasses([AssetClass::Bond]));

    expect($analysis->concentration->hhi)->toBeNull()
        ->and($analysis->contributions)->toBe([]);
});
