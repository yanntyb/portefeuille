<?php

use App\Filament\Pages\Dashboard;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Services\YahooFinanceService;

use function Pest\Livewire\livewire;

it('updates prices when securities are missing today price', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now()->subDay(),
        'close' => 100,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePrices')
        ->once()
        ->with(\Mockery::on(fn ($s) => $s->id === $security->id));

    livewire(Dashboard::class)->call('loadPrices');
});

it('skips price update when all securities have today price', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 100,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldNotReceive('fetchAndStorePrices');

    livewire(Dashboard::class)->call('loadPrices');
});

it('skips securities without ticker', function () {
    $securityWithTicker = Security::factory()->create(['ticker' => 'AAPL']);
    $securityWithoutTicker = Security::factory()->create(['ticker' => null]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityWithTicker->id,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $securityWithoutTicker->id,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePrices')
        ->once()
        ->with(\Mockery::on(fn ($s) => $s->id === $securityWithTicker->id));

    livewire(Dashboard::class)->call('loadPrices');
});

it('dispatches prices-updated event after updating', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    $this->mock(YahooFinanceService::class)
        ->shouldReceive('fetchAndStorePrices');

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertDispatched('prices-updated');
});

it('does not dispatch event when no update needed', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 100,
    ]);

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertNotDispatched('prices-updated');
});
