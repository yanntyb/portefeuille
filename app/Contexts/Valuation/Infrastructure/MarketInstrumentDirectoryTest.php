<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

it('maps asset ids to instrument names', function () {
    $a = Instrument::factory()->create(['name' => 'ACME']);
    $b = Instrument::factory()->create(['name' => 'Globex']);

    $names = app(InstrumentDirectoryPort::class)->namesFor([$a->id, $b->id, 999]);

    expect($names[$a->id])->toBe('ACME');
    expect($names[$b->id])->toBe('Globex');
    expect($names)->not->toHaveKey(999);
});

it('returns an empty map for no ids', function () {
    expect(app(InstrumentDirectoryPort::class)->namesFor([]))->toBe([]);
});
