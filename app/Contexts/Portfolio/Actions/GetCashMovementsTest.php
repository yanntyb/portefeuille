<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create();
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
});

it('traduit un achat en mouvement d\'espèces négatif', function () {
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-03-03',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    /** `includeAuto: false` isole l'achat saisi du versement déduit que l'observateur écrit avec. */
    $movements = app(GetCashMovements::class)($this->user->id, includeAuto: false);

    expect($movements)->toHaveCount(1);
    $movement = $movements[0];
    expect($movement)->toBeInstanceOf(CashMovementData::class)
        ->and($movement->date)->toBe('2026-03-03')
        ->and($movement->walletId)->toBe($this->wallet->id)
        ->and($movement->delta)->toBe(-1005.0)
        ->and($movement->isDeposit)->toBeFalse()
        ->and($movement->isWithdrawal)->toBeFalse();
});

it('porte l\'exposition d\'une vente sur le mouvement', function () {
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-03-03',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $movement = app(GetCashMovements::class)($this->user->id, includeAuto: false)[0];

    expect($movement->delta)->toBe(1000.0)
        ->and($movement->exposure)->toBe(AssetClass::Equity);
});

it('ne filtre pas les versements, qui n\'ont pas d\'actif', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 1000,
        'auto' => false,
    ]);

    $movement = app(GetCashMovements::class)($this->user->id, includeAuto: false)[0];

    expect($movement->exposure)->toBeNull()
        ->and($movement->isDeposit)->toBeTrue()
        ->and($movement->delta)->toBe(1000.0);
});

it('marque un retrait', function () {
    Transaction::factory()->withdrawal()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 300,
        'auto' => false,
    ]);

    $movement = app(GetCashMovements::class)($this->user->id, includeAuto: false)[0];

    expect($movement->isWithdrawal)->toBeTrue()
        ->and($movement->delta)->toBe(-300.0);
});

it('exclut les lignes déduites quand includeAuto est faux', function () {
    /** Un achat non couvert : l'observateur écrit lui-même le versement déduit qui le finance. */
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    expect(app(GetCashMovements::class)($this->user->id, includeAuto: false))->toHaveCount(1)
        ->and(app(GetCashMovements::class)($this->user->id))->toHaveCount(2);
});

it('ignore les transactions d\'un autre utilisateur', function () {
    $other = User::factory()->create();
    $otherWallet = Wallet::factory()->for($other)->create();
    Transaction::factory()->buy()->create([
        'user_id' => $other->id,
        'wallet_id' => $otherWallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-01-01',
        'quantity' => 1,
        'unit_price' => 10,
        'fees' => 0,
    ]);

    expect(app(GetCashMovements::class)($this->user->id))->toBe([]);
});

it('lit les mouvements une seule fois par utilisateur pour une même sollicitation', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 500,
        'auto' => false,
    ]);

    $action = app(GetCashMovements::class);
    $action($this->user->id);

    DB::enableQueryLog();
    $action($this->user->id);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('oublie son cache après forget', function () {
    $action = app(GetCashMovements::class);
    $action($this->user->id);

    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 500,
        'auto' => false,
    ]);

    $action->forget($this->user->id);

    expect($action($this->user->id))->toHaveCount(1);
});

it('is bound scoped so every resolution within a request shares the same memoised instance', function () {
    expect(app(GetCashMovements::class))->toBe(app(GetCashMovements::class));
});
