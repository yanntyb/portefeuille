<?php

use App\Filament\Pages\Dashboard;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Services\YahooFinanceService;
use App\Support\MarketCalendar;

use function Pest\Livewire\livewire;

it('updates prices when securities have no recent price', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => MarketCalendar::lastTradingDate()->subDay(),
        'close' => 100,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->andReturn(1);

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertDispatched('prices-updated');
});

it('skips price update when securities have a price on last trading date', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => MarketCalendar::lastTradingDate(),
        'close' => 100,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldNotReceive('fetchAndStorePricesBulk');

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertNotDispatched('prices-updated');
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
    $mock->shouldNotReceive('fetchAndStorePricesBulk');

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertNotDispatched('prices-updated');
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
    $mock->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->withArgs(function ($securities) use ($securityWithTicker) {
            return $securities->count() === 1 && $securities->first()->id === $securityWithTicker->id;
        })
        ->andReturn(1);

    livewire(Dashboard::class)
        ->call('loadPrices')
        ->assertDispatched('prices-updated');
});

it('dispatches prices-updated event after updating prices', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
    ]);

    $mock = $this->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')->andReturn(1);

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
