<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    $this->journal = app(GetTransactionJournal::class);
    $this->funding = Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2025-01-01', 'amount' => 1000000,
    ]);
});

it('rend les opérations du porteur, la plus récente en tête, date puis id', function () {
    $ancienne = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-10', 'quantity' => 1, 'unit_price' => 100,
    ]);
    $premiere = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-03-01', 'quantity' => 1, 'unit_price' => 100,
    ]);
    $seconde = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-03-01', 'quantity' => 1, 'unit_price' => 100,
    ]);

    $lines = ($this->journal)($this->user->id);

    expect(array_column($lines, 'id'))->toBe([$seconde->id, $premiere->id, $ancienne->id, $this->funding->id])
        ->and($lines[0]->assetName)->toBe('ACME')
        ->and($lines[0]->assetId)->toBe($this->asset->id)
        ->and($lines[0]->walletId)->toBe($this->wallet->id)
        ->and($lines[0]->date)->toBe('2026-03-01');
});

it('ne lit rien d\'un autre porteur', function () {
    Transaction::factory()->buy()->create(['asset_id' => $this->asset->id]);

    $lines = ($this->journal)($this->user->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->id)->toBe($this->funding->id);
});

it('marque une vente et garde son montant positif, frais déduits', function () {
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 2, 'unit_price' => 50, 'fees' => 1,
    ]);

    $line = ($this->journal)($this->user->id)[0];

    expect($line->isSell)->toBeTrue()
        ->and($line->type)->toBe('sell')
        ->and($line->total)->toBe(99.0);
});

it('garde un mouvement d\'espèces sans actif, nommé par personne', function () {
    $deposit = Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-01', 'amount' => 500,
    ]);

    $line = ($this->journal)($this->user->id)[0];

    expect($line->id)->toBe($deposit->id)
        ->and($line->assetId)->toBeNull()
        ->and($line->assetName)->toBeNull()
        ->and($line->total)->toBe(500.0);
});

it('écarte le cash et les autres classes sur un périmètre par classe', function () {
    $crypto = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin', 'ticker' => 'BTC']);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $crypto->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-03',
    ]);

    $lines = ($this->journal)($this->user->id, HoldingScope::ofClasses([AssetClass::Equity]));

    expect(array_column($lines, 'assetName'))->toBe(['ACME'])
        ->and($lines)->toHaveCount(1);
});

it('garde le cash de l\'enveloppe et écarte les autres enveloppes sur un périmètre par enveloppe', function () {
    $autre = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);
    $autreFinancing = Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $autre->id, 'date' => '2025-01-01', 'amount' => 1000000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $autre->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-03',
    ]);

    $lines = ($this->journal)($this->user->id, HoldingScope::ofWallet($this->wallet->id));

    expect($lines)->toHaveCount(3)
        ->and(array_unique(array_column($lines, 'walletId')))->toBe([$this->wallet->id])
        ->and($lines[0]->assetId)->toBeNull();
});

it('ne rend que les opérations d\'un actif sur forAsset, du porteur seulement', function () {
    $autreActif = Instrument::factory()->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $autreActif->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->buy()->create(['asset_id' => $this->asset->id]);

    $lines = $this->journal->forAsset($this->user->id, $this->asset->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->assetId)->toBe($this->asset->id);
});

it('nomme les actifs sans rouvrir une requête par ligne', function () {
    foreach (range(1, 5) as $i) {
        $asset = Instrument::factory()->create(['name' => "Titre {$i}", 'ticker' => "T{$i}"]);
        Transaction::factory()->buy()->create([
            'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $asset->id, 'date' => "2026-01-0{$i}",
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $lines = ($this->journal)($this->user->id);

    expect($lines)->toHaveCount(6)
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('porte les versements que le système a déduits, marqués auto', function () {
    $unfunded = Wallet::factory()->for($this->user)->create(['name' => 'Unfunded']);
    $buy = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $unfunded->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 1, 'unit_price' => 100,
    ]);

    $lines = ($this->journal)($this->user->id, HoldingScope::ofWallet($unfunded->id));

    $autoDeposit = collect($lines)->first(fn ($line) => $line->type === 'deposit' && $line->auto);

    expect($lines)->toHaveCount(2)
        ->and($autoDeposit)->not->toBeNull()
        ->and($autoDeposit->auto)->toBeTrue()
        ->and($autoDeposit->assetId)->toBeNull();
});
