<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Infrastructure\MemoizedPriceHistory;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

function countingPriceHistory(int &$calls): PriceHistoryPort
{
    return new class($calls) implements PriceHistoryPort
    {
        public function __construct(private int &$calls) {}

        public function forAssetsSince(array $assetIds, Carbon $since): array
        {
            $this->calls++;

            return array_map(
                fn (int $assetId): PriceRecordData => new PriceRecordData(
                    assetId: $assetId,
                    date: $since->toDateString(),
                    close: 100.0,
                ),
                $assetIds,
            );
        }
    };
}

it('ne lit qu\'une fois le même historique de prix', function () {
    $calls = 0;
    $prices = new MemoizedPriceHistory(countingPriceHistory($calls));

    $first = $prices->forAssetsSince([1, 2], Carbon::parse('2026-01-01'));
    $second = $prices->forAssetsSince([1, 2], Carbon::parse('2026-01-01'));

    expect($calls)->toBe(1)
        ->and($second)->toBe($first);
});

it('reconnaît la même demande quel que soit l\'ordre des actifs', function () {
    $calls = 0;
    $prices = new MemoizedPriceHistory(countingPriceHistory($calls));

    $prices->forAssetsSince([2, 1], Carbon::parse('2026-01-01'));
    $prices->forAssetsSince([1, 2], Carbon::parse('2026-01-01'));

    expect($calls)->toBe(1);
});

it('relit dès que la date de départ change', function () {
    $calls = 0;
    $prices = new MemoizedPriceHistory(countingPriceHistory($calls));

    $prices->forAssetsSince([1], Carbon::parse('2026-01-01'));
    $prices->forAssetsSince([1], Carbon::parse('2025-01-01'));

    expect($calls)->toBe(2);
});
