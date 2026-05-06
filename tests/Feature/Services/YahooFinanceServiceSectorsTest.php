<?php

use App\Enums\Sector;
use App\Models\Security;
use App\Models\SecuritySector;
use App\Services\YahooFinanceClient;
use App\Services\YahooFinanceService;

it('fetches and stores sectors for an ETF with multiple sectors', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchSectors')
            ->with('CW8.PA')
            ->andReturn([
                'technology' => 0.2283,
                'healthcare' => 0.1341,
                'financial_services' => 0.1556,
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(3);
    expect(SecuritySector::where('security_id', $security->id)->count())->toBe(3);

    $this->assertDatabaseHas('security_sectors', [
        'security_id' => $security->id,
        'sector' => 'technology',
        'weight' => 0.2283,
    ]);

    $this->assertDatabaseHas('security_sectors', [
        'security_id' => $security->id,
        'sector' => 'healthcare',
        'weight' => 0.1341,
    ]);
});

it('fetches and stores a single sector for a stock', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchSectors')
            ->with('AAPL')
            ->andReturn([
                'technology' => 1.0,
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(1);

    $sector = SecuritySector::where('security_id', $security->id)->first();

    expect($sector->sector)->toBe(Sector::Technology)
        ->and((float) $sector->weight)->toBe(1.0);
});

it('maps unknown sectors to Other', function () {
    $security = Security::factory()->create(['ticker' => 'TEST']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchSectors')
            ->with('TEST')
            ->andReturn([
                'unknown_sector' => 0.5,
                'technology' => 0.5,
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(2);

    $this->assertDatabaseHas('security_sectors', [
        'security_id' => $security->id,
        'sector' => Sector::Other->value,
    ]);
});

it('removes old sectors not present in the new result', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'sector' => Sector::Energy,
        'weight' => 0.1,
    ]);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchSectors')
            ->with('CW8.PA')
            ->andReturn([
                'technology' => 1.0,
            ]);
    });

    $service = app(YahooFinanceService::class);
    $service->fetchAndStoreSectors($security);

    expect(SecuritySector::where('security_id', $security->id)->count())->toBe(1);

    $this->assertDatabaseMissing('security_sectors', [
        'security_id' => $security->id,
        'sector' => Sector::Energy->value,
    ]);
});

it('resolves ticker if not set before fetching sectors', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => null]);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->withArgs(fn ($query, $fallback) => $query === 'FR0011871110')
            ->andReturn([
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ]);
        $mock->shouldReceive('fetchSectors')
            ->with('CW8.PA')
            ->andReturn(['technology' => 1.0]);
    });

    $service = app(YahooFinanceService::class);
    $service->fetchAndStoreSectors($security);

    expect($security->fresh()->ticker)->toBe('CW8.PA');
});

it('returns zero when no sector data is available', function () {
    $security = Security::factory()->create(['ticker' => 'TEST']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchSectors')
            ->with('TEST')
            ->andReturn([]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(0);
    expect(SecuritySector::where('security_id', $security->id)->count())->toBe(0);
});
