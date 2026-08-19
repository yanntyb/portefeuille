<?php

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Ports\IncomeSourcePort;
use Illuminate\Support\Carbon;

/**
 * Une source de revenu factice, pour prouver que l'agrégation ne connaît aucune source par son
 * nom : la même mécanique doit servir un dividende comme un loyer.
 */
function fakeIncomeSource(array $amounts, float $projection = 0.0): IncomeSourcePort
{
    return new class($amounts, $projection) implements IncomeSourcePort
    {
        /** @param list<float> $amounts */
        public function __construct(private array $amounts, private float $projection) {}

        public function source(): IncomeSource
        {
            return IncomeSource::Dividend;
        }

        /** @return list<IncomeReceiptData> */
        public function receiptsFor(int $userId): array
        {
            return array_map(fn (float $amount): IncomeReceiptData => new IncomeReceiptData(
                source: IncomeSource::Dividend,
                date: Carbon::parse('2026-03-05'),
                amount: $amount,
            ), $this->amounts);
        }

        public function projectedAnnualFor(int $userId): float
        {
            return $this->projection;
        }
    };
}

it('concatène les reçus de toutes les sources', function () {
    $registry = new IncomeSourceRegistry([fakeIncomeSource([10.0, 20.0]), fakeIncomeSource([5.0])]);

    expect(collect($registry->receiptsFor(1))->pluck('amount')->all())->toBe([10.0, 20.0, 5.0]);
});

it('rend un tableau vide sans aucune source', function () {
    expect((new IncomeSourceRegistry([]))->receiptsFor(1))->toBe([]);
});

it('somme les projections annuelles de toutes les sources', function () {
    // Les projections ne se déduisent pas des reçus : une source les annonce, le registre les
    // additionne sans les recalculer.
    $registry = new IncomeSourceRegistry([
        fakeIncomeSource([10.0], projection: 120.0),
        fakeIncomeSource([5.0], projection: 30.0),
    ]);

    expect($registry->projectedAnnualFor(1))->toBe(150.0);
});

it('ne projette rien sans aucune source', function () {
    expect((new IncomeSourceRegistry([]))->projectedAnnualFor(1))->toBe(0.0);
});
