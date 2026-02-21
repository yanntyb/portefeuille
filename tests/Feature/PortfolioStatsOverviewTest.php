<?php

use App\Filament\Widgets\Dashboard\PortfolioStatsOverview;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('computes valuation, invested, and plus-value correctly', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $widget = livewire(PortfolioStatsOverview::class);
    $widget->assertOk();

    $stats = invade($widget->instance())->getStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toContain('1,200')
        ->and($stats[1]->getValue())->toContain('1,005')
        ->and($stats[2]->getValue())->toContain('195');
});

it('aggregates across PEA and CTO accounts', function () {
    $securityPea = Security::factory()->create();
    $securityCto = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $securityPea->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $securityCto->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityPea->id,
        'date' => now(),
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $securityCto->id,
        'date' => now(),
        'close' => 250,
    ]);

    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    // Valuation = (10 * 120) + (5 * 250) = 1200 + 1250 = 2450
    expect($stats[0]->getValue())->toContain('2,450')
        // Invested = (10 * 100) + (5 * 200) = 1000 + 1000 = 2000
        ->and($stats[1]->getValue())->toContain('2,000')
        // Plus-value = 2450 - 2000 = 450
        ->and($stats[2]->getValue())->toContain('450');
});

it('shows success color when plus-value is positive', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 150,
    ]);

    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats[2]->getColor())->toBe('success');
});

it('shows danger color when plus-value is negative', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 80,
    ]);

    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats[2]->getColor())->toBe('danger');
});

it('shows description with repartition by account type', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats[0]->getDescription())->toContain('PEA')
        ->and($stats[0]->getDescription())->toContain('1,200');
});

it('returns zero values when no data exists', function () {
    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toContain('0');
});
