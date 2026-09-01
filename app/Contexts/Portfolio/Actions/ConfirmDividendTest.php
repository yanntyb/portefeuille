<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\ConfirmDividend;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\CashLedger;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-01', 'quantity' => 100, 'unit_price' => 10, 'fees' => 0,
    ]);

    Dividend::query()->create([
        'asset_id' => $this->asset->id, 'ex_date' => '2026-02-01', 'amount_per_share' => 0.5,
    ]);
});

it('encaisse un dividende attendu et crédite le compte espèces', function () {
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);

    $movements = app(GetCashMovements::class)($this->user->id);

    expect(Transaction::query()->where('type', TransactionType::Dividend)->count())->toBe(1)
        ->and(app(CashLedger::class)->balanceAt($movements, $this->wallet->id, '2026-02-01'))->toBe(50.0);
});

it('retient le montant net saisi plutôt que le montant calculé', function () {
    /** 100 × 0,50 € = 50 € bruts ; 34,90 € nets réellement reçus. */
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 34.90);

    expect((float) Transaction::query()->where('type', TransactionType::Dividend)->value('amount'))->toBe(34.90);
});

it('sort le détachement encaissé de la dérivation', function () {
    $before = app(GetIncomeSummary::class)($this->user->id)->totalReceived;

    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 34.90);

    /** Le détachement est encaissé : il compte pour son montant réel, jamais deux fois. */
    expect($before)->toBe(50.0)
        ->and(app(GetIncomeSummary::class)($this->user->id)->totalReceived)->toBe(34.90);
});

it('donne une ligne par enveloppe détentrice', function () {
    $cto = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $cto->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-01', 'quantity' => 40, 'unit_price' => 10, 'fees' => 0,
    ]);

    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);
    app(ConfirmDividend::class)($this->user->id, $cto->id, $this->asset->id, '2026-02-01', 20.0);

    expect(Transaction::query()->where('type', TransactionType::Dividend)->count())->toBe(2);
});

it('refuse un dividende déjà encaissé pour la même enveloppe et la même date', function () {
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);

    expect(fn () => app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0))
        ->toThrow(RuntimeException::class);
});
