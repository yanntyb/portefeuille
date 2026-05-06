<?php

use App\Domains\Portfolio\Filament\Widgets\Dashboard\PortfolioStatsOverview;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

use function Pest\Livewire\livewire;

it('computes valuation, plus-value, and fees correctly', function () {
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
        ->and($stats[0]->getLabel())->toBe('Valorisation')
        ->and($stats[0]->getValue())->toContain('1,200')
        ->and($stats[1]->getLabel())->toBe('Plus-value')
        ->and($stats[1]->getValue())->toContain('195')
        ->and($stats[2]->getLabel())->toBe('Frais')
        ->and($stats[2]->getValue())->toContain('5');
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
        // Plus-value = 2450 - 2000 = 450
        ->and($stats[1]->getValue())->toContain('450');
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

    expect($stats[1]->getColor())->toBe('success');
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

    expect($stats[1]->getColor())->toBe('danger');
});

it('displays plus-value percentage in description', function () {
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

    // Plus-value = 200, invested = 1000, percentage = 20%
    expect($stats[1]->getDescription())->toContain('20.00')
        ->and($stats[1]->getDescription())->toContain('%');
});

it('displays fees percentage in description', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 15,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats[2]->getLabel())->toBe('Frais')
        ->and($stats[2]->getValue())->toContain('15')
        ->and($stats[2]->getDescription())->toContain('%')
        ->and($stats[2]->getColor())->toBe('danger');
});

it('returns zero values when no data exists', function () {
    $stats = invade(livewire(PortfolioStatsOverview::class)->instance())->getStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toContain('0');
});
