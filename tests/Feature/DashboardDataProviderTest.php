<?php

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\DashboardDataProvider;

it('returns securities with latest price for a given wallet', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 150]);

    $provider = app(DashboardDataProvider::class);
    $securities = $provider->securitiesForWallet($peaWallet);

    expect($securities)->toHaveCount(1)
        ->and($securities->first()->id)->toBe($security->id)
        ->and($securities->first()->latestPrice)->not->toBeNull()
        ->and((float) $securities->first()->latestPrice->close)->toBe(150.0);
});

it('caches results within the same instance', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 100]);

    $provider = app(DashboardDataProvider::class);

    $first = $provider->securitiesForWallet($peaWallet);
    $second = $provider->securitiesForWallet($peaWallet);

    expect($first)->toBe($second);
});

it('separates securities by wallet', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $ctoWallet = Wallet::factory()->cto()->create();
    $peaSecurity = Security::factory()->create();
    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $peaSecurity->id]);
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $ctoSecurity->id]);

    $provider = app(DashboardDataProvider::class);

    $peaSecurities = $provider->securitiesForWallet($peaWallet);
    $ctoSecurities = $provider->securitiesForWallet($ctoWallet);

    expect($peaSecurities)->toHaveCount(1)
        ->and($peaSecurities->first()->id)->toBe($peaSecurity->id)
        ->and($ctoSecurities)->toHaveCount(1)
        ->and($ctoSecurities->first()->id)->toBe($ctoSecurity->id);
});

it('is registered as a scoped singleton', function () {
    $first = app(DashboardDataProvider::class);
    $second = app(DashboardDataProvider::class);

    expect($first)->toBe($second);
});
