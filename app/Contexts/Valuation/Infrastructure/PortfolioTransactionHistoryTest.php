<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

it('maps a user transactions to records ordered by date', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['asset_class' => AssetClass::Equity]);

    /** Le cash déposé couvre l'achat : aucun versement déduit ne s'ajoute derrière. */
    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2025-12-31', 'amount' => 2000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'fees' => 2, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 4, 'unit_price' => 150, 'date' => '2026-02-01',
    ]);

    $records = app(TransactionHistoryPort::class)->forUser($user->id);
    $deposit = collect($records)->firstWhere('type', TransactionType::Deposit);
    $buy = collect($records)->firstWhere('type', TransactionType::Buy);
    $sell = collect($records)->firstWhere('type', TransactionType::Sell);

    expect($records)->toHaveCount(3)
        ->and($records[0]->date->format('Y-m-d'))->toBe('2025-12-31')
        ->and($deposit->assetId)->toBeNull()
        ->and($deposit->amount)->toBe(2000.0)
        ->and($buy->isSell)->toBeFalse()
        ->and($buy->quantity)->toBe(10.0)
        ->and($buy->unitPrice)->toBe(100.0)
        ->and($buy->fees)->toBe(2.0)
        ->and($buy->exposure)->toBe(AssetClass::Equity)
        ->and($sell->isSell)->toBeTrue()
        ->and($sell->assetId)->toBe($asset->id)
        ->and($sell->quantity)->toBe(4.0);
});

it('inclut les versements et retraits, qui n\'ont pas d\'actif', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->withdrawal()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-02-01', 'amount' => 300,
    ]);

    $records = app(TransactionHistoryPort::class)->forUser($user->id);

    expect($records)->toHaveCount(2)
        ->and($records[0]->assetId)->toBeNull()
        ->and($records[0]->type)->toBe(TransactionType::Deposit)
        ->and($records[0]->amount)->toBe(1000.0)
        ->and($records[0]->exposure)->toBeNull()
        ->and($records[1]->type)->toBe(TransactionType::Withdrawal)
        ->and($records[1]->amount)->toBe(300.0);
});

it('porte l\'enveloppe de chaque transaction', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $records = app(PortfolioTransactionHistory::class)->forUser($user->id);

    expect($records)->not->toBeEmpty()
        ->and($records[0]->walletId)->toBe($wallet->id);
});

it('porte l\'enveloppe d\'un versement, qui n\'a pourtant aucun actif', function () {
    ['user' => $user] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'date' => '2026-02-01',
        'amount' => 500,
    ]);

    $records = app(PortfolioTransactionHistory::class)->forUser($user->id);
    /**
     * L'achat de `portfolioFixture()` n'est couvert par aucun versement saisi : `RecomputeCashDeposits`
     * lui déduit donc automatiquement un versement dans SON enveloppe, qui porte lui aussi un
     * `assetId` nul. Filtrer sur la date isole le versement de ce test, sans le confondre avec
     * cette ligne déduite.
     */
    $deposit = array_values(array_filter(
        $records,
        fn ($record): bool => $record->assetId === null && $record->date->format('Y-m-d') === '2026-02-01',
    ))[0];

    expect($deposit->walletId)->toBe($other->id);
});
