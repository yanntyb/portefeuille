<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Services\SingleSecurityStatsProvider;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

it('returns all zeros when no transactions exist', function () {
    $security = Security::factory()->create();

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['totalQuantity'])->toEqual(0)
        ->and($stats['pru'])->toEqual(0)
        ->and($stats['totalFees'])->toEqual(0)
        ->and($stats['totalInvested'])->toEqual(0)
        ->and($stats['totalRealizedGain'])->toEqual(0)
        ->and($stats['valuation'])->toEqual(0)
        ->and($stats['plusValue'])->toEqual(0)
        ->and($stats['close'])->toBeNull();
});

it('calculates total quantity from buy and sell transactions', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create(['security_id' => $security->id, 'quantity' => 10, 'unit_price' => 100]);
    Transaction::factory()->pea()->create(['security_id' => $security->id, 'quantity' => 5, 'unit_price' => 120]);
    Transaction::factory()->pea()->sell()->create(['security_id' => $security->id, 'quantity' => 3]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['totalQuantity'])->toBe(12.0); // 10 + 5 - 3
});

it('calculates weighted average price (PRU)', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create(['security_id' => $security->id, 'quantity' => 10, 'unit_price' => 100]);
    Transaction::factory()->pea()->create(['security_id' => $security->id, 'quantity' => 5, 'unit_price' => 110]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    // PRU = (10*100 + 5*110) / (10 + 5) = 1550 / 15 = 103.33
    expect($stats['pru'])->toBeGreaterThan(103)
        ->and($stats['pru'])->toBeLessThan(104);
});

it('filters transactions by wallet when walletId provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet1 = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);
    $wallet2 = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);

    $security = Security::factory()->create();

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet1->id,
        'quantity' => 10,
        'unit_price' => 100,
        'user_id' => $user->id,
    ]);
    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet2->id,
        'quantity' => 5,
        'unit_price' => 100,
        'user_id' => $user->id,
    ]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, $wallet1->id);

    expect($stats['totalQuantity'])->toBe(10.0);
});

it('includes fees in total invested', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 25.50,
    ]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['totalInvested'])->toBeGreaterThan(1000);
    expect($stats['feesPercentage'])->toBeGreaterThan(2.0);
});

it('returns zero valuation when no latest price exists', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['valuation'])->toEqual(0)
        ->and($stats['plusValue'])->toBeLessThan(0); // negative because no price
});

it('calculates valuation with latest price', function () {
    $security = Security::factory()->create();
    SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 150, 'date' => now()]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['valuation'])->toBe(1500.0) // 10 * 150
        ->and($stats['plusValue'])->toBeGreaterThan(400) // with invested cost
        ->and($stats['plusValuePercentage'])->toBeGreaterThan(30);
});

it('caches results by security id and wallet id', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id, 'quantity' => 10, 'unit_price' => 100]);

    $provider = new SingleSecurityStatsProvider;

    $stats1 = $provider->computeStats($security, null);
    $stats2 = $provider->computeStats($security, null);

    expect($stats1)->toBe($stats2);
});

it('calculates realized gain from sell transactions', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);
    Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
    ]);

    $provider = new SingleSecurityStatsProvider;
    $stats = $provider->computeStats($security, null);

    expect($stats['totalRealizedGain'])->toBeGreaterThan(0.0);
});
