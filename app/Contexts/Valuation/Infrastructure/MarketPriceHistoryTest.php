<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

it('maps prices for the given assets since a date', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 120]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2025-01-01', 'close' => 50]); // before since

    $records = app(PriceHistoryPort::class)->forAssetsSince([$asset->id], Carbon::parse('2026-01-01'));

    expect($records)->toHaveCount(2)
        ->and($records[0]->assetId)->toBe($asset->id)
        ->and(collect($records)->pluck('close')->all())->toContain(100.0, 120.0);
});
