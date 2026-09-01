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

/**
 * `seriesFor()` applique la même formule que `snapshotFor()`, point par point : aucun test ne la
 * vérifiait directement, alors qu'une bande de graphe fausse ne fait échouer aucun test et que la
 * tâche 14 va figer un hash d'instantané sur cette valeur.
 */
it('suit la même formule que l\'instantané à chaque date, avant et après une vente', function () {
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
    /**
     * Un cours à chacune des deux dates qui comptent : sans lui, `BuildEvolutionSeries` ne peut
     * poser aucun point (sa grille vient des prix, pas des transactions), et le coût de revient
     * resterait invisible à la série des liquidités.
     */
    Price::factory()->create(['asset_id' => $this->asset->id, 'date' => '2026-01-02', 'close' => 100.0]);
    Price::factory()->create(['asset_id' => $this->asset->id, 'date' => '2026-02-01', 'close' => 120.0]);

    $series = app(CashClass::class)->seriesFor($this->user->id);

    expect($series->labels)->toBe(['2026-01-01', '2026-01-02', '2026-02-01']);

    /** Jour du dépôt : rien n'est encore en titres, tout le dépôt est encore du cash. */
    expect($series->value[0])->toBe(1000.0)
        ->and($series->invested[0])->toBe(1000.0);

    /** Avant la vente : la caisse est vide, l'apport est tout entier immobilisé dans le titre. */
    expect($series->value[1])->toBe(0.0)
        ->and($series->invested[1])->toBe(0.0);

    /**
     * Après la vente partielle : 600 € encaissés sur 5 titres, les 5 restants valant encore 500 €
     * de coût de revient — seuls 100 € de plus-value sont réellement dans la caisse.
     */
    expect($series->value[2])->toBe(600.0)
        ->and($series->invested[2])->toBe(500.0)
        ->and(round($series->value[2] - $series->invested[2], 2))->toBe(100.0);
});

it('rend une série vide sans aucune transaction', function () {
    $series = app(CashClass::class)->seriesFor($this->user->id);

    expect($series->labels)->toBe([])
        ->and($series->value)->toBe([])
        ->and($series->invested)->toBe([]);
});

it('ne laisse aucun coût fantôme quand aucune vente n\'a jamais rendu de capital', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);

    $series = app(CashClass::class)->seriesFor($this->user->id);

    expect($series->labels)->toBe(['2026-01-01', '2026-01-02']);

    /** Jour du dépôt : tout est encore cash. */
    expect($series->value[0])->toBe(1000.0)
        ->and($series->invested[0])->toBe(1000.0);

    /**
     * Après l'achat, sans aucun cours connu pour le titre : `BuildEvolutionSeries` ne pose aucun
     * point, et le coût de revient reporté vaut 0 — mais la caisse est vide aussi, donc l'investi
     * reste borné à 0 plutôt que de devenir négatif.
     */
    expect($series->value[1])->toBe(0.0)
        ->and($series->invested[1])->toBe(0.0);
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
