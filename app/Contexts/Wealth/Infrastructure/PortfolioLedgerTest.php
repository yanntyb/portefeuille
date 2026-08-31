<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\Wealth\Infrastructure\PortfolioLedger;

beforeEach(function () {
    $this->ledger = new PortfolioLedger(new TransactionFlow);
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'Compte-titres']);
    $this->asset = Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'LED.PA']);
});

it('names the asset of each line without reopening a query per line', function () {
    $buy = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'quantity' => 10,
        'unit_price' => 80,
        'fees' => 1,
        'date' => '2026-01-01',
    ]);

    $lines = $this->ledger->transactionsFor($this->user->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->id)->toBe($buy->id)
        ->and($lines[0]->walletId)->toBe($this->wallet->id)
        ->and($lines[0]->assetId)->toBe($this->asset->id)
        ->and($lines[0]->assetName)->toBe('ACME')
        ->and($lines[0]->typeLabel)->toBe('Achat')
        ->and($lines[0]->isSell)->toBeFalse()
        /** Flux réel de l'achat : 800 € de titres, plus 1 € de courtage. */
        ->and($lines[0]->total)->toBe(801.0);
});

it('orders by date then by id, newest first', function () {
    $collection = collect(['2025-06-01', '2026-03-01', '2026-03-01'])
        ->map(fn (string $date) => Transaction::factory()->buy()->create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'asset_id' => $this->asset->id,
            'date' => $date,
        ]));

    $lines = $this->ledger->transactionsFor($this->user->id);

    /** Deux opérations du même jour : l'identifiant tranche, la plus récemment saisie en tête. */
    expect(array_map(fn ($line): int => $line->id, $lines))
        ->toBe([$collection[2]->id, $collection[1]->id, $collection[0]->id]);
});

it('skips the transaction that carries no asset', function () {
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => null,
        'date' => '2026-01-01',
    ]);

    /** La jointure la coupe : une opération sans actif n'a rien à nommer. */
    expect($this->ledger->transactionsFor($this->user->id))->toBe([]);
});

it('reads nothing of another user', function () {
    $other = User::factory()->create();
    $otherWallet = Wallet::factory()->for($other)->create(['name' => 'Ailleurs']);

    Transaction::factory()->buy()->create([
        'user_id' => $other->id,
        'wallet_id' => $otherWallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-01-01',
    ]);

    expect($this->ledger->transactionsFor($this->user->id))->toBe([]);
});

it('marks a sell and keeps its amount positive', function () {
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'quantity' => 3,
        'unit_price' => 120,
        'fees' => 2,
        'date' => '2026-06-01',
    ]);

    $lines = $this->ledger->transactionsFor($this->user->id);

    /** Les frais grèvent ce qu'une vente rapporte ; le sens du flux se lit sur `isSell`. */
    expect($lines[0]->isSell)->toBeTrue()
        ->and($lines[0]->typeLabel)->toBe('Vente')
        ->and($lines[0]->total)->toBe(358.0);
});
