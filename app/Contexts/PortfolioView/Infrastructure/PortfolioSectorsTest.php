<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->sectors = app(SectorBreakdownPort::class);
});

it('rend la répartition sectorielle du portefeuille en parts libellées', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'quantity' => 2, 'avg_cost' => 80,
    ]);
    SectorAllocation::factory()->create([
        'asset_id' => $instrument->id, 'sector' => Sector::Technology, 'weight' => 1,
    ]);

    $slices = $this->sectors->breakdownFor($user->id);

    expect($slices)->toHaveCount(1);
    expect($slices[0]->label)->toBe('Technologie');
    expect($slices[0]->value)->toBe(200.0);
    expect($slices[0]->pct)->toBe(100.0);
    expect($slices[0]->color)->toBe(Sector::Technology->getColor());
});

it('rend une répartition vide pour un utilisateur inconnu', function () {
    expect($this->sectors->breakdownFor(999))->toBe([]);
});
