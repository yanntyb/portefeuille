<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Actions\GetClassCatalog;

beforeEach(function (): void {
    $this->catalog = app(GetClassCatalog::class);
    $this->user = User::factory()->create();
});

it('liste un instrument de la classe jamais acheté', function () {
    Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM']);

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->name)->toBe('ACME')
        ->and($lines[0]->held)->toBeFalse()
        ->and($lines[0]->quantity)->toBeNull()
        ->and($lines[0]->marketValue)->toBeNull();
});

it('écarte les instruments d\'une autre exposition', function () {
    Instrument::factory()->create(['name' => 'ACME']);
    Instrument::factory()->ofType(InstrumentType::Commodity)->create(['name' => 'Or']);

    $lines = ($this->catalog)($this->user->id, AssetClass::Commodity);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->name)->toBe('Or');
});

it('marque la position détenue et la valorise au dernier cours', function () {
    $instrument = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 120]);
    $wallet = Wallet::factory()->for($this->user)->create();
    Holding::factory()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect($lines[0]->held)->toBeTrue()
        ->and($lines[0]->lastPrice)->toBe(120.0)
        ->and($lines[0]->quantity)->toBe(10.0)
        ->and($lines[0]->marketValue)->toBe(1200.0);
});

it('somme les enveloppes en une seule ligne de catalogue', function () {
    $instrument = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-07-01', 'close' => 100]);

    foreach ([4, 6] as $quantity) {
        $wallet = Wallet::factory()->for($this->user)->create();
        Holding::factory()->create([
            'user_id' => $this->user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $instrument->id,
            'quantity' => $quantity,
            'avg_cost' => 80,
        ]);
    }

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->quantity)->toBe(10.0);
});

it('laisse cours et valorisation nuls pour un actif sans aucun cours', function () {
    $instrument = Instrument::factory()->create(['name' => 'ACME']);
    $wallet = Wallet::factory()->for($this->user)->create();
    Holding::factory()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect($lines[0]->held)->toBeTrue()
        ->and($lines[0]->lastPrice)->toBeNull()
        ->and($lines[0]->marketValue)->toBeNull();
});

it('ignore les positions d\'un autre utilisateur', function () {
    $instrument = Instrument::factory()->create(['name' => 'ACME']);
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($other)->create();
    Holding::factory()->create([
        'user_id' => $other->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect($lines[0]->held)->toBeFalse();
});

it('range le catalogue par nom', function () {
    Instrument::factory()->create(['name' => 'Zeta']);
    Instrument::factory()->create(['name' => 'Alpha']);

    $lines = ($this->catalog)($this->user->id, AssetClass::Equity);

    expect(array_map(fn ($line): string => $line->name, $lines))->toBe(['Alpha', 'Zeta']);
});
