<?php

use App\Enums\AccountType;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Services\DashboardDataProvider;

it('returns securities with latest price for a given account type', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 150]);

    $provider = app(DashboardDataProvider::class);
    $securities = $provider->securitiesForAccount(AccountType::Pea);

    expect($securities)->toHaveCount(1)
        ->and($securities->first()->id)->toBe($security->id)
        ->and($securities->first()->latestPrice)->not->toBeNull()
        ->and((float) $securities->first()->latestPrice->close)->toBe(150.0);
});

it('caches results within the same instance', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 100]);

    $provider = app(DashboardDataProvider::class);

    $first = $provider->securitiesForAccount(AccountType::Pea);
    $second = $provider->securitiesForAccount(AccountType::Pea);

    expect($first)->toBe($second);
});

it('separates securities by account type', function () {
    $peaSecurity = Security::factory()->create();
    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $peaSecurity->id]);
    Transaction::factory()->cto()->create(['security_id' => $ctoSecurity->id]);

    $provider = app(DashboardDataProvider::class);

    $peaSecurities = $provider->securitiesForAccount(AccountType::Pea);
    $ctoSecurities = $provider->securitiesForAccount(AccountType::Cto);

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
