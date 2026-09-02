<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\TransactionFlow;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;
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

    /**
     * Un achat non couvert par aucun dépôt fait déduire un versement du même jour
     * (`RecomputeCashDeposits`) : deux lignes désormais, la ligne d'espèces sans actif comprise.
     */
    $lines = collect($this->ledger->transactionsFor($this->user->id));

    expect($lines)->toHaveCount(2);

    $line = $lines->firstWhere('id', $buy->id);

    expect($line->walletId)->toBe($this->wallet->id)
        ->and($line->assetId)->toBe($this->asset->id)
        ->and($line->assetName)->toBe('ACME')
        ->and($line->typeLabel)->toBe('Achat')
        ->and($line->isSell)->toBeFalse()
        /** Flux réel de l'achat : 800 € de titres, plus 1 € de courtage. */
        ->and($line->total)->toBe(801.0);
});

it('orders by date then by id, newest first', function () {
    $collection = collect(['2025-06-01', '2026-03-01', '2026-03-01'])
        ->map(fn (string $date) => Transaction::factory()->buy()->create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'asset_id' => $this->asset->id,
            'date' => $date,
        ]));

    /** Les versements déduits par les achats s'intercalent : on ne trie ici que les achats. */
    $lines = collect($this->ledger->transactionsFor($this->user->id))
        ->where('type', 'buy')
        ->values();

    /** Deux opérations du même jour : l'identifiant tranche, la plus récemment saisie en tête. */
    expect($lines->pluck('id')->all())
        ->toBe([$collection[2]->id, $collection[1]->id, $collection[0]->id]);
});

it('keeps a transaction that carries no asset, as a cash movement', function () {
    $deposit = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => null,
        'type' => TransactionType::Deposit,
        'quantity' => null,
        'unit_price' => null,
        'fees' => 0,
        'amount' => 1000,
        'date' => '2026-01-01',
    ]);

    /** `leftJoin` et non `join` : un versement sans actif ne disparaît plus du journal. */
    $lines = $this->ledger->transactionsFor($this->user->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->id)->toBe($deposit->id)
        ->and($lines[0]->assetId)->toBeNull()
        ->and($lines[0]->assetName)->toBeNull()
        ->and($lines[0]->typeLabel)->toBe('Versement')
        ->and($lines[0]->type)->toBe('deposit')
        ->and($lines[0]->total)->toBe(1000.0);
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

it('ne rend que les opérations de l\'enveloppe demandée, la plus récente en tête', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 5,
        'unit_price' => 90,
        'date' => '2026-03-01',
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'unit_price' => 95,
        'date' => '2026-04-01',
    ]);

    $lines = app(PortfolioLedger::class)->transactionsForWallet($user->id, $wallet->id);

    /**
     * L'achat de la fixture et celui ajouté ici sont chacun non couverts par un dépôt : deux
     * versements déduits par `RecomputeCashDeposits` s'ajoutent aux deux achats, quatre lignes.
     */
    expect($lines)->toHaveCount(4)
        ->and($lines[0]->date)->toBe('2026-03-01')
        ->and(array_unique(array_map(fn (WealthTransactionLineData $line): int => $line->walletId, $lines)))->toBe([$wallet->id]);
});

it('ne rend rien pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    expect(app(PortfolioLedger::class)->transactionsForWallet($user->id, $foreign->id))->toBe([]);
});
