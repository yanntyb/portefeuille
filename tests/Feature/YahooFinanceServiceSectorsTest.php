<?php

use App\Enums\Sector;
use App\Models\Security;
use App\Models\SecuritySector;
use App\Services\YahooFinanceService;
use Illuminate\Support\Facades\Process;

it('fetches and stores sectors for an ETF with multiple sectors', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    Process::fake([
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'technology' => 0.2283,
                'healthcare' => 0.1341,
                'financial_services' => 0.1556,
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
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

    Process::fake([
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'technology' => 1.0,
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(1);

    $sector = SecuritySector::where('security_id', $security->id)->first();

    expect($sector->sector)->toBe(Sector::Technology)
        ->and((float) $sector->weight)->toBe(1.0);
});

it('maps unknown sectors to Other', function () {
    $security = Security::factory()->create(['ticker' => 'TEST']);

    Process::fake([
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'unknown_sector' => 0.5,
                'technology' => 0.5,
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
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

    Process::fake([
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'technology' => 1.0,
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStoreSectors($security);

    expect(SecuritySector::where('security_id', $security->id)->count())->toBe(1);

    $this->assertDatabaseMissing('security_sectors', [
        'security_id' => $security->id,
        'sector' => Sector::Energy->value,
    ]);
});

it('resolves ticker if not set before fetching sectors', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => null]);

    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ],
        ])),
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => ['technology' => 1.0],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStoreSectors($security);

    expect($security->fresh()->ticker)->toBe('CW8.PA');
});

it('returns zero when no sector data is available', function () {
    $security = Security::factory()->create(['ticker' => 'TEST']);

    Process::fake([
        '*fetch_sectors.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStoreSectors($security);

    expect($count)->toBe(0);
    expect(SecuritySector::where('security_id', $security->id)->count())->toBe(0);
});
