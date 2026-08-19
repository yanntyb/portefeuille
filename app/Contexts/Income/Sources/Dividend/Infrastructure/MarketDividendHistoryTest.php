<?php

use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;

it('traduit les détachements du contexte Marché en enregistrements de calcul', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.51,
    ]);

    $records = app(DividendHistoryPort::class)->forAssets([$instrument->id]);

    expect($records)->toHaveCount(1)
        ->and($records[0]->assetId)->toBe($instrument->id)
        ->and($records[0]->exDate->format('Y-m-d'))->toBe('2026-03-05')
        ->and($records[0]->amountPerShare)->toBe(0.51);
});

it('rend le nom des instruments demandés', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    expect(app(DividendHistoryPort::class)->namesFor([$instrument->id]))
        ->toBe([$instrument->id => 'Amundi MSCI World']);
});
