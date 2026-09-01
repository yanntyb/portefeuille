<?php

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use Illuminate\Support\Carbon;

function boughtOn(string $date, float $quantity, int $assetId = 1, int $walletId = 1): PositionRecordData
{
    return new PositionRecordData($assetId, $walletId, Carbon::parse($date), false, $quantity);
}

function soldOn(string $date, float $quantity, int $assetId = 1, int $walletId = 1): PositionRecordData
{
    return new PositionRecordData($assetId, $walletId, Carbon::parse($date), true, $quantity);
}

function detachment(string $date, float $amountPerShare, int $assetId = 1): DividendRecordData
{
    return new DividendRecordData($assetId, Carbon::parse($date), $amountPerShare);
}

it('multiplie le détachement par la quantité détenue à l\'ex-date', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->quantity)->toBe(10.0)
        ->and($receipts[0]->amountPerShare)->toBe(0.5)
        ->and($receipts[0]->amount)->toBe(5.0)
        ->and($receipts[0]->exDate)->toBe('2026-03-05');
});

it('exclut un achat passé le jour même du détachement', function () {
    // Détenir le titre le jour de l'ex-date ne donne pas droit au dividende : il faut le
    // détenir la veille.
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-03-05', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toBe([]);
});

it('retient la quantité restante après une vente partielle', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 4.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts[0]->quantity)->toBe(6.0)
        ->and($receipts[0]->amount)->toBe(3.0);
});

it('ignore un détachement sur une position soldée', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toBe([]);
});

it('reprend le versement après un rachat', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 10.0), boughtOn('2026-04-01', 3.0)],
        [detachment('2026-03-05', 0.5), detachment('2026-06-04', 1.0)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->exDate)->toBe('2026-06-04')
        ->and($receipts[0]->amount)->toBe(3.0);
});

it('agrège les enveloppes : la source ne voit qu\'une quantité par actif', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), boughtOn('2026-01-15', 5.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts[0]->quantity)->toBe(15.0)
        ->and($receipts[0]->amount)->toBe(7.5);
});

it('ne mélange pas les actifs', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0, assetId: 1), boughtOn('2026-01-10', 100.0, assetId: 2)],
        [detachment('2026-03-05', 0.5, assetId: 2)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->assetId)->toBe(2)
        ->and($receipts[0]->amount)->toBe(50.0);
});

it('rend un reçu par enveloppe détentrice, jamais un montant agrégé', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0, walletId: 1), boughtOn('2026-01-15', 5.0, walletId: 2)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toHaveCount(2);

    $byWallet = [];
    foreach ($receipts as $receipt) {
        $byWallet[$receipt->walletId] = $receipt;
    }

    expect($byWallet[1]->quantity)->toBe(10.0)
        ->and($byWallet[1]->amount)->toBe(5.0)
        ->and($byWallet[2]->quantity)->toBe(5.0)
        ->and($byWallet[2]->amount)->toBe(2.5);
});

it('ignore un détachement sur un actif jamais acheté', function () {
    expect((new DividendCalculator)->receipts([], [detachment('2026-03-05', 0.5)]))->toBe([]);
});

it('rend un tableau vide sans détachement', function () {
    expect((new DividendCalculator)->receipts([boughtOn('2026-01-10', 10.0)], []))->toBe([]);
});

it('trie les reçus du plus récent au plus ancien', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-01', 10.0)],
        [detachment('2026-03-05', 0.5), detachment('2026-09-03', 0.6), detachment('2026-06-04', 0.4)],
    );

    expect(array_map(fn ($receipt): string => $receipt->exDate, $receipts))
        ->toBe(['2026-09-03', '2026-06-04', '2026-03-05']);
});

it('arrondit le montant perçu au centime', function () {
    // 3 × 0.125 = 0.375 : round() rend 0,38, une troncature rendrait 0,37. Un montant qui
    // s'arrête avant la troisième décimale (comme 0.123456, dont round() et une troncature
    // rendent tous deux 0,37) ne distinguerait pas les deux.
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 3.0)],
        [detachment('2026-03-05', 0.125)],
    );

    expect($receipts[0]->amount)->toBe(0.38);
});
