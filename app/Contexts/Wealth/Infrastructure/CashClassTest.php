<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Infrastructure\CashClass;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);
});

it('vaut le solde d\'espèces de toutes les enveloppes', function () {
    $cto = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);

    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $cto->id, 'date' => '2026-01-01', 'amount' => 500,
    ]);

    expect(app(CashClass::class)->snapshotFor($this->user->id)->value)->toBe(1500.0);
});

/**
 * L'apport suit l'argent : tant qu'il est en titres, il est imputé à l'exposition (via son coût
 * de revient) ; dès que les titres sont vendus, le capital revient aux liquidités. L'étiquette
 * d'origine du FIFO (`CashLedger::compositionAt()`) ne convient pas : elle dirait d'où vient un
 * euro, pas s'il est capital ou gain, et le produit d'une vente est les deux à la fois.
 */
it('n\'investit rien tant que l\'apport est encore immobilisé en titres', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);

    $snapshot = app(CashClass::class)->snapshotFor($this->user->id);

    /** Caisse vide : les 1 000 € d'apport sont tout entiers dans le titre détenu. */
    expect($snapshot->value)->toBe(0.0)
        ->and($snapshot->invested)->toBe(0.0);
});

it('n\'investit que la part encore issue d\'un apport, et porte le reste en gain', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);

    $snapshot = app(CashClass::class)->snapshotFor($this->user->id);

    /** 1 200 € en caisse, dont 1 000 € d'apport : les 200 € de plus-value dorment là. */
    expect($snapshot->value)->toBe(1200.0)
        ->and($snapshot->invested)->toBe(1000.0);
});

it('ne suit que la plus-value réellement encaissée sur une vente partielle', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 5, 'unit_price' => 120, 'fees' => 0,
    ]);
    /** Le coût de revient des 5 titres restants ne se lit qu'à travers un dernier cours connu. */
    Price::factory()->create(['asset_id' => $this->asset->id, 'close' => 150.0]);

    $snapshot = app(CashClass::class)->snapshotFor($this->user->id);

    /**
     * 600 € encaissés sur 5 titres vendus, les 5 restants valant encore 500 € de coût de revient :
     * seuls 100 € de plus-value ont vraiment été réalisés, pas les 600 € bruts de la vente.
     */
    expect($snapshot->value)->toBe(600.0)
        ->and($snapshot->invested)->toBe(500.0);
});

it('investit tout ce qu\'un retrait laisse d\'apport, sans titre pour l\'immobiliser', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->withdrawal()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-01', 'amount' => 300,
    ]);

    $snapshot = app(CashClass::class)->snapshotFor($this->user->id);

    expect($snapshot->value)->toBe(700.0)
        ->and($snapshot->invested)->toBe(700.0);
});

it('ne déclare aucune origine de revenu', function () {
    expect(app(CashClass::class)->incomeLabel())->toBeNull()
        ->and(app(CashClass::class)->monthlyIncomeFor($this->user->id))->toBe(0.0);
});

it('vient en dernier dans le registre', function () {
    $keys = array_map(fn ($class): string => $class->key(), app(AssetClassRegistry::class)->all());

    expect(end($keys))->toBe('cash')
        ->and(array_slice($keys, 0, 4))->toBe(['equity', 'bond', 'commodity', 'crypto']);
});
