<?php

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Infrastructure\PortfolioAccounts;
use App\Contexts\PortfolioView\Infrastructure\PortfolioTotals;

it('rend l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $account = app(PortfolioAccounts::class)->accountFor($user->id, $wallet->id);

    expect($account?->walletId)->toBe($wallet->id)
        ->and($account?->marketValue)->toBe(1000.0);
});

it('ne rend rien pour une enveloppe inconnue ou tenue par un autre', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    $accounts = app(PortfolioAccounts::class);

    expect($accounts->accountFor($user->id, 999999))->toBeNull()
        ->and($accounts->accountFor($user->id, $foreign->id))->toBeNull();
});

it('ne rend que les positions de l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 400]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 300,
    ]);

    $positions = app(PortfolioTotals::class)->overviewFor($user->id, HoldingScope::ofWallet($wallet->id))->holdings;

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id)
        ->and($positions[0]->walletId)->toBe($wallet->id);
});

it('ventile l\'enveloppe par classe d\'actif, la plus grosse part en tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 250]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 250,
    ]);

    $slices = app(PortfolioTotals::class)->classBreakdownFor($user->id, HoldingScope::ofWallet($wallet->id));

    expect($slices)->toHaveCount(2)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->label)->toBe('Actions')
        ->and($slices[0]->value)->toBe(1000.0)
        ->and($slices[0]->share)->toBe(80.0)
        ->and($slices[1]->key)->toBe('crypto')
        ->and($slices[1]->share)->toBe(20.0);
});

it('ne ventile rien pour une enveloppe sans position', function () {
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    expect(app(PortfolioTotals::class)->classBreakdownFor($user->id, HoldingScope::ofWallet($empty->id)))->toBe([]);
});

it('ne sert pas une enveloppe sans position ni espèces', function () {
    ['user' => $user] = portfolioFixture();
    // Sans la moindre transaction : celle-ci n'a même pas le versement déduit automatique que
    // déclenche l'observateur de transaction — elle est réellement vide, en base comme dans le
    // domaine, et `GetAccountBreakdown` ne lui donne donc pas de ligne.
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    expect(app(PortfolioAccounts::class)->accountFor($user->id, $empty->id))->toBeNull();
});
