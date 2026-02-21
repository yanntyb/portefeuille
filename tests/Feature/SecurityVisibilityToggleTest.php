<?php

use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Filament\Widgets\Securities\SecurityStatsOverview;
use App\Filament\Widgets\Securities\ValuationChartWidget;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('initializes shownSecurityIds with all security ids on mount', function () {
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security1->id]);
    Transaction::factory()->pea()->create(['security_id' => $security2->id]);

    $page = livewire(ListPeaSecurities::class);

    expect($page->get('shownSecurityIds'))
        ->toContain($security1->id)
        ->toContain($security2->id);
});

it('removes a security id when toggling a visible security', function () {
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security1->id]);
    Transaction::factory()->pea()->create(['security_id' => $security2->id]);

    $page = livewire(ListPeaSecurities::class)
        ->call('toggleSecurity', $security1->id);

    expect($page->get('shownSecurityIds'))
        ->not->toContain($security1->id)
        ->toContain($security2->id);
});

it('adds back a security id when toggling a hidden security', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    $page = livewire(ListPeaSecurities::class)
        ->call('toggleSecurity', $security->id)
        ->call('toggleSecurity', $security->id);

    expect($page->get('shownSecurityIds'))
        ->toContain($security->id);
});

it('dispatches security-visibility-changed event on toggle', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->call('toggleSecurity', $security->id)
        ->assertDispatched('security-visibility-changed');
});

it('works on CTO page as well', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    $page = livewire(ListCtoSecurities::class);

    expect($page->get('shownSecurityIds'))
        ->toContain($security->id);

    $page->call('toggleSecurity', $security->id);

    expect($page->get('shownSecurityIds'))
        ->not->toContain($security->id);
});

it('filters stats widget to only shown securities', function () {
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security1->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security2->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security1->id,
        'date' => now(),
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security2->id,
        'date' => now(),
        'close' => 250,
    ]);

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => ListPeaSecurities::class,
        'shownSecurityIds' => [$security1->id],
    ]);

    $stats = invade($widget->instance())->getStats();

    // Only security1: valuation = 10 * 120 = 1200
    expect($stats[0]->getValue())->toContain('1,200');
});

it('filters chart widget to only shown securities', function () {
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security1->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security2->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security1->id,
        'date' => '2024-01-15',
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security2->id,
        'date' => '2024-01-15',
        'close' => 250,
    ]);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
        'shownSecurityIds' => [$security1->id],
    ]);

    $data = invade($widget->instance())->getData();

    // Only security1: valuation = 10 * 120 = 1200
    expect($data['datasets'][0]['data'][0])->toBe(1200.0);
});

it('shows empty stats when all securities are hidden', function () {
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
        'shownSecurityIds' => [],
    ]);

    $stats = invade($widget->instance())->getStats();

    expect($stats[0]->getValue())->toContain('0');
});

it('shows empty chart when all securities are hidden', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-15',
        'close' => 120,
    ]);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => ListPeaSecurities::class,
        'shownSecurityIds' => [],
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});
