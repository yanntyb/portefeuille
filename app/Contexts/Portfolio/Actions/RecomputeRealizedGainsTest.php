<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\RecomputeRealizedGains;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'Compte-titres']);
    $this->asset = Instrument::factory()->create(['ticker' => 'REC.PA']);
});

function line(object $ctx, string $type, float $quantity, float $price, string $date): Transaction
{
    return Transaction::factory()->create([
        'user_id' => $ctx->user->id,
        'wallet_id' => $ctx->wallet->id,
        'asset_id' => $ctx->asset->id,
        'type' => $type,
        'quantity' => $quantity,
        'unit_price' => $price,
        'fees' => 0,
        'date' => $date,
    ]);
}

function recompute(object $ctx): void
{
    app(RecomputeRealizedGains::class)($ctx->user->id, $ctx->asset->id, $ctx->wallet->id);
}

it('recomputes a sell after the buy it leaned on changed price', function () {
    line($this, 'buy', 10, 100, '2026-01-01');
    $sell = line($this, 'sell', 5, 150, '2026-03-01');

    expect((float) $sell->fresh()->realized_gain)->toBe(250.0); // (150-100)*5

    /**
     * Correction du prix d'achat sans passer par l'observateur : ce que l'action doit rattraper,
     * c'est un `realized_gain` déjà écrit sur une autre ligne que celle qui a bougé.
     */
    Transaction::query()
        ->where('type', 'buy')
        ->update(['unit_price' => 120]);

    recompute($this);

    expect((float) $sell->fresh()->realized_gain)->toBe(150.0); // (150-120)*5
});

it('recomputes every sell of the pair, not only the last', function () {
    line($this, 'buy', 20, 100, '2026-01-01');
    $first = line($this, 'sell', 5, 150, '2026-02-01');
    $second = line($this, 'sell', 5, 200, '2026-03-01');

    Transaction::query()->where('type', 'buy')->update(['unit_price' => 110]);

    recompute($this);

    expect((float) $first->fresh()->realized_gain)->toBe(200.0)  // (150-110)*5
        ->and((float) $second->fresh()->realized_gain)->toBe(450.0); // (200-110)*5
});

it('leaves the buys alone', function () {
    $buy = line($this, 'buy', 10, 100, '2026-01-01');
    line($this, 'sell', 5, 150, '2026-03-01');

    recompute($this);

    /** Un achat ne réalise rien : sa colonne reste nulle, elle ne devient pas zéro. */
    expect($buy->fresh()->realized_gain)->toBeNull();
});

it('ignores the sells of another wallet', function () {
    $elsewhere = Wallet::factory()->for($this->user)->create(['name' => 'Ailleurs']);

    line($this, 'buy', 10, 100, '2026-01-01');
    $sell = line($this, 'sell', 5, 150, '2026-03-01');

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $elsewhere->id,
        'asset_id' => $this->asset->id,
        'type' => 'buy',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2026-01-01',
    ]);

    $other = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $elsewhere->id,
        'asset_id' => $this->asset->id,
        'type' => 'sell',
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 0,
        'date' => '2026-03-01',
    ]);

    Transaction::query()
        ->where('wallet_id', $this->wallet->id)
        ->where('type', 'buy')
        ->update(['unit_price' => 120]);

    recompute($this);

    expect((float) $sell->fresh()->realized_gain)->toBe(150.0)
        ->and((float) $other->fresh()->realized_gain)->toBe(250.0);
});
