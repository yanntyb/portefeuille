<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Services\PortfolioPerformanceCalculator;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;
use Illuminate\Support\Carbon;

it('computes basic return without cash flows', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-01-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $securities = Security::query()
        ->forWallet(Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']))
        ->with('latestPrice')
        ->get();

    $returns = app(PortfolioPerformanceCalculator::class)->computeReturns($securities);

    // Valo début (3 mois) = 10 * 100 = 1000, Valo fin = 10 * 120 = 1200
    // Flux nets = 0, Return = (1200 - 1000 - 0) / (1000 + 0) * 100 = 20%
    expect($returns['3m'])->toBe(20.0);
});

it('computes return with cash flows during period', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    // Transaction initiale avant la période de 6 mois (2024-12-15)
    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'date' => '2024-11-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    // Achat supplémentaire pendant la période
    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'date' => '2025-04-01',
        'quantity' => 5,
        'unit_price' => 110,
        'fees' => 5,
    ]);

    // Prix au début de la période 6m (proche du 2024-12-15)
    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-12-15',
        'close' => 100,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $securities = Security::query()
        ->forWallet(Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']))
        ->with('latestPrice')
        ->get();

    $returns = app(PortfolioPerformanceCalculator::class)->computeReturns($securities);

    // TWR : prix passe de 100 à 120 = +20%, indépendamment de l'achat supplémentaire
    // Sous-période 1 : r1 = (10*100)/(10*100) - 1 = 0%
    // Sous-période 2 : r2 = (15*120)/(15*100) - 1 = 20%
    // TWR = (1+0)(1+0.20) - 1 = 20%
    expect($returns['6m'])->toBe(20.0);
});

it('returns null when period predates first transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'date' => '2025-05-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $securities = Security::query()
        ->forWallet(Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']))
        ->with('latestPrice')
        ->get();

    $returns = app(PortfolioPerformanceCalculator::class)->computeReturns($securities);

    // 1 an = 2024-06-15, aucune transaction ni prix avant ça → null
    expect($returns['1y'])->toBeNull();
});

it('uses closest available price when exact start date has no price', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow('2025-06-15');

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'user_id' => $user->id,
        'date' => '2025-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    // Pas de prix au 2025-03-15 exactement, mais un prix au 2025-03-10
    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-03-10',
        'close' => 105,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2025-06-15',
        'close' => 120,
    ]);

    $securities = Security::query()
        ->forWallet(Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']))
        ->with('latestPrice')
        ->get();

    $returns = app(PortfolioPerformanceCalculator::class)->computeReturns($securities);

    // 3 mois = 2025-03-15, prix le plus proche <= 2025-03-15 est le 2025-03-10 (close=105)
    // Valo début = 10 * 105 = 1050, Valo fin = 10 * 120 = 1200
    // Return = (1200 - 1050) / 1050 * 100 ≈ 14.29%
    expect($returns['3m'])->toBe(14.29);
});
