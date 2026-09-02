<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\PortfolioView\Infrastructure\PortfolioTransactions;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\TransactionFlow;

beforeEach(function () {
    $this->adapter = new PortfolioTransactions(new TransactionFlow);
});

it('returns transactions for a user and asset, newest first', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80, 'fees' => 1]);
    $sell = Transaction::factory()->sell()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-03-01', 'quantity' => 4, 'unit_price' => 100, 'fees' => 2]);

    $lines = $this->adapter->transactionsFor($user->id, $asset->id);

    expect($lines)->toHaveCount(2);
    /** L'identifiant et l'enveloppe ouvrent l'édition depuis la liste. */
    expect($lines[0]->id)->toBe($sell->id);
    expect($lines[0]->walletId)->toBe($wallet->id);
    expect($lines[0]->date)->toBe('2026-03-01');
    expect($lines[0]->isSell)->toBeTrue();
    expect($lines[0]->typeLabel)->toBe('Vente');
    /** Le montant d'une ligne est son flux réel : les frais grèvent la vente et alourdissent l'achat. */
    expect($lines[0]->total)->toBe(398.0);
    expect($lines[1]->date)->toBe('2026-01-01');
    expect($lines[1]->typeLabel)->toBe('Achat');
    expect($lines[1]->total)->toBe(801.0);
});

it('returns the transactions of a whole exposure, each line naming its asset', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $equity = Instrument::factory()->create(['name' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $crypto = Instrument::factory()->create(['name' => 'Bitcoin', 'asset_class' => AssetClass::Crypto]);
    $buy = Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $equity->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80, 'fees' => 1]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $crypto->id, 'date' => '2026-03-01']);

    $lines = $this->adapter->transactionsForClass($user->id, AssetClass::Equity);

    /** Une opération d'une autre exposition n'a rien à faire sur la page : la jointure la coupe. */
    expect($lines)->toHaveCount(1);
    expect($lines[0]->id)->toBe($buy->id);
    expect($lines[0]->walletId)->toBe($wallet->id);
    expect($lines[0]->assetId)->toBe($equity->id);
    expect($lines[0]->assetName)->toBe('ACME');
    /** Le montant d'une ligne est son flux réel : les frais alourdissent l'achat. */
    expect($lines[0]->total)->toBe(801.0);
});

it('orders the exposure history newest first and excludes other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $otherWallet = Wallet::factory()->for($other)->create();
    $asset = Instrument::factory()->create(['asset_class' => AssetClass::Equity]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2025-01-02']);
    Transaction::factory()->sell()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-03-01']);
    Transaction::factory()->buy()->create(['user_id' => $other->id, 'wallet_id' => $otherWallet->id, 'asset_id' => $asset->id, 'date' => '2026-06-01']);

    $lines = $this->adapter->transactionsForClass($user->id, AssetClass::Equity);

    expect(array_map(fn ($line) => $line->date, $lines))->toBe(['2026-03-01', '2025-01-02']);
});

it('excludes other users and other assets', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    $otherAsset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $otherAsset->id]);

    expect($this->adapter->transactionsFor($user->id, $asset->id))->toBeEmpty();
});
