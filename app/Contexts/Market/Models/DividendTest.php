<?php

use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\QueryException;

it('cast la date de détachement et garde six décimales sur le montant', function () {
    $dividend = Dividend::factory()->create([
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.123456,
    ])->fresh();

    expect($dividend->ex_date->format('Y-m-d'))->toBe('2026-03-05')
        ->and((float) $dividend->amount_per_share)->toBe(0.123456);
});

it('appartient à son instrument', function () {
    $instrument = Instrument::factory()->create();
    $dividend = Dividend::factory()->create(['asset_id' => $instrument->id]);

    expect($dividend->instrument->id)->toBe($instrument->id);
});

it('refuse deux détachements le même jour pour le même actif', function () {
    $instrument = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);

    expect(fn () => Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
    ]))->toThrow(QueryException::class);
});
