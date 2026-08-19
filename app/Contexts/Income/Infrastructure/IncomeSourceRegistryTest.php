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
function fakeIncomeSource(float ...$amounts): IncomeSourcePort
{
    return new class($amounts) implements IncomeSourcePort
    {
        /** @param list<float> $amounts */
        public function __construct(private array $amounts) {}

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
    };
}

it('concatène les reçus de toutes les sources', function () {
    $registry = new IncomeSourceRegistry([fakeIncomeSource(10.0, 20.0), fakeIncomeSource(5.0)]);

    expect(collect($registry->receiptsFor(1))->pluck('amount')->all())->toBe([10.0, 20.0, 5.0]);
});

it('rend un tableau vide sans aucune source', function () {
    expect((new IncomeSourceRegistry([]))->receiptsFor(1))->toBe([]);
});
