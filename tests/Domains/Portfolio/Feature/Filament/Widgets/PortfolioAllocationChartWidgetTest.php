<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\PortfolioAllocationChartWidget;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns labels matching account types and correct valuations', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $securityPea = Security::factory()->create();
    $securityCto = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $securityPea->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $securityCto->id,
        'quantity' => 5,
        'unit_price' => 200,
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

    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);
    $ctoWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'CTO']);

    $widget = livewire(PortfolioAllocationChartWidget::class);
    $widget->assertOk();

    $data = invade($widget->instance())->getData();

    $peaIndex = array_search('PEA', $data['labels']);
    $ctoIndex = array_search('CTO', $data['labels']);

    expect($data['labels'])->toContain('PEA', 'CTO')
        ->and($data['datasets'][0]['data'][$peaIndex])->toBe(1200.0)
        ->and($data['datasets'][0]['data'][$ctoIndex])->toBe(1250.0);
});

it('returns zero valuations when no data exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Wallet::factory()->pea()->create();
    Wallet::factory()->cto()->create();

    $data = invade(livewire(PortfolioAllocationChartWidget::class)->instance())->getData();

    expect($data['labels'])->toContain('PEA', 'CTO')
        ->and($data['datasets'][0]['data'])->each->toBe(0.0);
});

it('only includes accounts with securities that have prices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    // No price created for this security
    Wallet::factory()->cto()->create();

    $data = invade(livewire(PortfolioAllocationChartWidget::class)->instance())->getData();

    expect($data['datasets'][0]['data'])->each->toBe(0.0);
});
