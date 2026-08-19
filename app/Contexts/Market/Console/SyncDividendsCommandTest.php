<?php

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Models\Instrument;

it('refuse une date de début mal formée', function () {
    $this->artisan('market:sync-dividends', ['--since' => '19/08/2026'])
        ->expectsOutputToContain('Format attendu : AAAA-MM-JJ.')
        ->assertFailed();
});

it('refuse un identifiant d\'actif inconnu', function () {
    $this->artisan('market:sync-dividends', ['--asset' => '404'])
        ->expectsOutputToContain('Aucun instrument ne porte l\'identifiant « 404 ».')
        ->assertFailed();
});

it('prévient quand aucun instrument n\'est à synchroniser', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData);

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('Aucun instrument à synchroniser.')
        ->assertSuccessful();
});

it('détaille le rapport par ticker', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData(synced: ['CW8.PA' => 2], failed: ['DEAD.PA']));

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('CW8.PA : 2 détachements')
        ->expectsOutputToContain('DEAD.PA : échec')
        ->expectsOutputToContain('2 instruments, 1 synchronisé, 1 échec')
        ->assertSuccessful();
});

it('échoue quand la récupération échoue en totalité', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData(failed: ['CW8.PA'], error: 'yfinance rate limited'));

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('yfinance rate limited')
        ->assertFailed();
});

it('passe l\'actif demandé à l\'action', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);

    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->once()
        ->with($instrument->id, null)
        ->andReturn(new DividendSyncReportData(synced: ['CW8.PA' => 1]));

    $this->artisan('market:sync-dividends', ['--asset' => (string) $instrument->id])->assertSuccessful();
});
