<?php

use App\Filament\Pages\Dashboard;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
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
    $mock->shouldReceive('fetchAndStoreSectors');

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
    $mock->shouldReceive('fetchAndStoreSectors');

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
    $mock->shouldReceive('fetchAndStoreSectors');

    livewire(Dashboard::class)->call('loadPrices');
});

it('dispatches prices-updated event after updating prices', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePrices');
    $mock->shouldReceive('fetchAndStoreSectors');

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertDispatched('prices-updated');
});

it('dispatches prices-updated event after updating sectors only', function () {
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
    $mock->shouldReceive('fetchAndStoreSectors')->once();

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

    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'updated_at' => now(),
    ]);

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertNotDispatched('prices-updated');
});

it('fetches sectors independently from prices', function () {
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
    $mock->shouldReceive('fetchAndStoreSectors')
        ->once()
        ->with(\Mockery::on(fn ($s) => $s->id === $security->id));

    livewire(Dashboard::class)->call('loadPrices');
});

it('skips sectors when recently updated', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'updated_at' => now()->subDays(3),
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePrices');
    $mock->shouldNotReceive('fetchAndStoreSectors');

    livewire(Dashboard::class)->call('loadPrices');
});
