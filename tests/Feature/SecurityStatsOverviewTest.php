<?php

use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('can render on the PEA list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(SecurityStatsOverview::class);
});

it('can render on the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(ListCtoSecurities::class)
        ->assertOk()
        ->assertSeeLivewire(SecurityStatsOverview::class);
});

it('computes valuation and plus-value correctly', function () {
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

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $stats = invade($widget->instance())->getStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toContain('1,200')
        ->and($stats[1]->getValue())->toContain('195')
        ->and($stats[2]->getLabel())->toBe('Frais')
        ->and($stats[2]->getValue())->toContain('5');
});

it('displays percentage alongside plus-value', function () {
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

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $stats = invade($widget->instance())->getStats();

    // Plus-value = 1200 - 1000 = 200, percentage = 20%
    expect($stats[1]->getValue())->toContain('200')
        ->and($stats[1]->getValue())->toContain('20.00')
        ->and($stats[1]->getValue())->toContain('%');
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

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $stats = invade($widget->instance())->getStats();

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

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $stats = invade($widget->instance())->getStats();

    expect($stats[1]->getColor())->toBe('danger');
});

it('displays fees with percentage', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 10,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $stats = invade($widget->instance())->getStats();

    // Fees = 10, totalInvested = 10*100 + 10 = 1010, percentage = 10/1010 * 100 ≈ 0.99%
    expect($stats[2]->getLabel())->toBe('Frais')
        ->and($stats[2]->getValue())->toContain('10')
        ->and($stats[2]->getValue())->toContain('%')
        ->and($stats[2]->getColor())->toBe('danger');
});

it('returns empty stats when no securities exist', function () {
    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
    ]);

    $widget->assertOk();

    $stats = invade($widget->instance())->getStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toContain('0');
});
