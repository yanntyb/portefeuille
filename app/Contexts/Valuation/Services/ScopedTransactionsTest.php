<?php

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Services\ScopedTransactions;
use Illuminate\Support\Carbon;

/** Un annuaire figé : l'actif 1 est une action, l'actif 2 une crypto. */
$directory = new class implements InstrumentDirectoryPort
{
    /** @param  list<AssetClass>  $classes */
    public function idsOfClasses(array $classes): array
    {
        return in_array(AssetClass::Equity, $classes, strict: true) ? [1] : [2];
    }

    /** @param  list<int>  $assetIds */
    public function namesFor(array $assetIds): array
    {
        return [];
    }
};

$buy = fn (int $assetId, int $walletId): TransactionRecordData => new TransactionRecordData(
    date: Carbon::parse('2026-01-01'),
    assetId: $assetId,
    walletId: $walletId,
    type: TransactionType::Buy,
    isSell: false,
    quantity: 1.0,
    unitPrice: 100.0,
    fees: 0.0,
);

$deposit = fn (int $walletId): TransactionRecordData => new TransactionRecordData(
    date: Carbon::parse('2026-01-01'),
    assetId: null,
    walletId: $walletId,
    type: TransactionType::Deposit,
    isSell: false,
    quantity: 0.0,
    unitPrice: 0.0,
    fees: 0.0,
    amount: 500.0,
);

it('rend le journal entier sans périmètre', function () use ($directory, $buy, $deposit) {
    $journal = [$buy(1, 10), $buy(2, 11), $deposit(11)];

    expect((new ScopedTransactions($directory))->within($journal, HoldingScope::all()))->toBe($journal);
});

/**
 * Le filtre par classe laisse passer le cash : un versement n'appartient à aucune exposition, et
 * l'écarter bâtirait un cash sur les seuls achats de la classe — négatif en permanence, puisqu'il
 * ne verrait jamais les versements qui les ont financés.
 */
it('laisse le cash traverser le filtre par classe', function () use ($directory, $buy, $deposit) {
    $kept = (new ScopedTransactions($directory))->within(
        [$buy(1, 10), $buy(2, 11), $deposit(11)],
        HoldingScope::ofClasses([AssetClass::Equity]),
    );

    expect($kept)->toHaveCount(2)
        ->and($kept[0]->assetId)->toBe(1)
        ->and($kept[1]->assetId)->toBeNull();
});

/**
 * Le filtre par enveloppe, lui, écarte franchement le cash des autres comptes : il est tenu par
 * wallet, et l'attribuer aux voisins gonflerait leur apport d'un argent qu'ils n'ont jamais vu.
 * Les deux filtres ne se comportent pas pareil, à dessein.
 */
it('écarte le cash des enveloppes voisines', function () use ($directory, $buy, $deposit) {
    $kept = (new ScopedTransactions($directory))->within(
        [$buy(1, 10), $buy(2, 11), $deposit(11), $deposit(10)],
        HoldingScope::ofWallet(10),
    );

    expect($kept)->toHaveCount(2)
        ->and($kept[0]->walletId)->toBe(10)
        ->and($kept[1]->walletId)->toBe(10);
});

it('cumule les deux filtres', function () use ($directory, $buy, $deposit) {
    $kept = (new ScopedTransactions($directory))->within(
        [$buy(1, 10), $buy(1, 11), $buy(2, 10), $deposit(11)],
        HoldingScope::of([AssetClass::Equity], 10),
    );

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->assetId)->toBe(1)
        ->and($kept[0]->walletId)->toBe(10);
});
