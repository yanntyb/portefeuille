<?php

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\PriceRecordData as P;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

function tx(string $date, int $assetId, bool $isSell, float $qty, float $price, float $fees = 0.0): TransactionRecordData
{
    $type = $isSell ? TransactionType::Sell : TransactionType::Buy;

    return new TransactionRecordData(
        date: Carbon::parse($date),
        assetId: $assetId,
        type: $type,
        isSell: $isSell,
        quantity: $qty,
        unitPrice: $price,
        fees: $fees,
    );
}

/** Un versement, seul mouvement d'espèces sans quantité ni actif détenu. */
function deposit(string $date, float $amount): TransactionRecordData
{
    return new TransactionRecordData(
        date: Carbon::parse($date),
        assetId: null,
        type: TransactionType::Deposit,
        isSell: false,
        quantity: 0.0,
        unitPrice: 0.0,
        fees: 0.0,
        amount: $amount,
    );
}

it('returns an empty series without transactions', function () {
    expect((new ValuationCalculator)->calculateDaily([], []))->toEqual(
        ValuationSeriesData::empty()
    );
});

it('values a single buy across two price dates', function () {
    $series = (new ValuationCalculator)->calculateDaily(
        [tx('2026-01-01', 1, false, 10, 100)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 120)],
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('reduces value and invested after a sell', function () {
    $series = (new ValuationCalculator)->calculateDaily(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 1, true, 4, 150)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 150)],
    );

    // day 1: qty 10 @100 = 1000, invested 1000
    // day 2: qty 6 @150 = 900, invested 1000 - (4 * PRU100) = 600
    expect($series->valuations)->toBe([1000.0, 900.0])
        ->and($series->invested)->toBe([1000.0, 600.0]);
});

it('ignores an asset that has no price', function () {
    $series = (new ValuationCalculator)->calculateDaily(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 5, 50)],
        [new PriceRecordData(1, '2026-01-01', 100)], // only asset 1 priced
    );

    // asset 2 contributes 0 to value (no close), invested still counts both buys
    expect($series->valuations)->toBe([1000.0])
        ->and($series->invested)->toBe([1250.0]);
});

it('orders same-day buys before sells regardless of input order', function () {
    $prices = [new PriceRecordData(1, '2026-01-01', 150)];
    $buy = tx('2026-01-01', 1, false, 10, 100);
    $sell = tx('2026-01-01', 1, true, 4, 150);

    $sellFirst = (new ValuationCalculator)->calculateDaily([$sell, $buy], $prices);
    $buyFirst = (new ValuationCalculator)->calculateDaily([$buy, $sell], $prices);

    // both orderings must agree: qty 6 @150 = 900 ; invested 1000 - (4 × PRU 100) = 600
    expect($sellFirst->valuations)->toBe([900.0])
        ->and($sellFirst->invested)->toBe([600.0])
        ->and($buyFirst->valuations)->toBe([900.0])
        ->and($buyFirst->invested)->toBe([600.0]);
});

it('exposes the unit price aligned with the valuation labels', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            type: TransactionType::Buy,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [
        new PriceRecordData(assetId: 1, date: '2026-01-01', close: 100.0),
        new PriceRecordData(assetId: 1, date: '2026-01-02', close: 110.0),
        new PriceRecordData(assetId: 1, date: '2026-01-03', close: 90.0),
    ];

    $series = (new ValuationCalculator)->calculateDaily($transactions, $prices);

    expect($series->prices)->toBe([100.0, 110.0, 90.0])
        ->and($series->prices)->toHaveCount(count($series->labels))
        ->and($series->valuations)->toBe([1000.0, 1100.0, 900.0]);
});

it('calculateDaily returns one point per price day without downsampling', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            type: TransactionType::Buy,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [];
    for ($d = 1; $d <= 250; $d++) {
        $prices[] = new PriceRecordData(assetId: 1, date: Carbon::parse('2026-01-01')->addDays($d - 1)->format('Y-m-d'), close: 100.0 + $d);
    }

    $daily = (new ValuationCalculator)->calculateDaily($transactions, $prices);

    expect($daily->labels)->toHaveCount(250)
        ->and($daily->prices)->toHaveCount(250);
});

it('windows the series to the requested range', function () {
    $labels = [];
    $series = [];
    for ($d = 0; $d < 400; $d++) {
        $labels[] = Carbon::parse('2025-01-01')->addDays($d)->format('Y-m-d');
    }
    $values = array_map(fn (int $i): float => (float) ($i + 1), array_keys($labels));
    $daily = new ValuationSeriesData($labels, $values, $values, $values);

    $windowed = (new ValuationCalculator)->windowAndAggregate($daily, 1, ValuationGranularity::Day);

    $lastDate = Carbon::parse($labels[399]);
    $cutoff = $lastDate->copy()->subMonthsNoOverflow(1)->format('Y-m-d');
    expect($windowed->labels[0])->toBeGreaterThanOrEqual($cutoff)
        ->and($windowed->labels[count($windowed->labels) - 1])->toBe($labels[399])
        ->and(count($windowed->labels))->toBeLessThan(400);
});

it('aggregates by keeping the last point of each month bucket', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31', '2026-02-05', '2026-02-28'];
    $values = [1.0, 2.0, 3.0, 4.0, 5.0];
    $daily = new ValuationSeriesData($labels, $values, $values, $values);

    $monthly = (new ValuationCalculator)->windowAndAggregate($daily, null, ValuationGranularity::Month);

    expect($monthly->labels)->toBe(['2026-01-31', '2026-02-28'])
        ->and($monthly->valuations)->toBe([3.0, 5.0])
        ->and($monthly->prices)->toBe([3.0, 5.0]);
});

it('keeps every point when granularity is Day', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31'];
    $values = [1.0, 2.0, 3.0];
    $daily = new ValuationSeriesData($labels, $values, $values, $values);

    $result = (new ValuationCalculator)->windowAndAggregate($daily, null, ValuationGranularity::Day);

    expect($result->labels)->toBe($labels);
});

it('does not let the contribution date inflate the performance', function () {
    // Marché +10 % deux jours de suite, avec un apport de 10 000 € le premier jour.
    // TWR = 1,1 × 1,1 - 1 = +21 %, là où gain / valeur de début donnerait +1021 %.
    $daily = new ValuationSeriesData(
        ['2026-01-01', '2026-01-02', '2026-01-03'],
        [100.0, 10110.0, 11121.0],
        [100.0, 10100.0, 10100.0],
        [1.0, 1.1, 1.21],
    );

    $window = (new ValuationCalculator)->returnOverWindow($daily, '2026-01-01');

    expect($window->pct)->toBe(21.0)
        ->and($window->valueStart)->toBe(100.0)
        ->and($window->contributions)->toBe(10000.0)
        ->and($window->pnl)->toBe(1021.0);
});

it('skips the steps where the position was empty', function () {
    // Tout vendu au prix de revient les jours 2 et 3, racheté au prix de revient le jour 4 :
    // aucune division par zéro, et aucune performance à compter.
    $daily = new ValuationSeriesData(
        ['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'],
        [1000.0, 0.0, 0.0, 1200.0],
        [1000.0, 0.0, 0.0, 1200.0],
        [100.0, 0.0, 0.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01')->pct)->toBe(0.0);
});

it('exposes the window start, value, contributions and gain', function () {
    // Pas 1 : (1250 - 1000 - 200) / 1000 = +5 %. Pas 2 : (1400 - 1250) / 1250 = +12 %.
    // TWR = 1,05 × 1,12 - 1 = +17,6 %.
    $daily = new ValuationSeriesData(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 1250.0, 1400.0],
        [1000.0, 1200.0, 1200.0],
        [100.0, 110.0, 120.0],
    );

    $window = (new ValuationCalculator)->returnOverWindow($daily, '2026-01-01');

    expect($window->startDate)->toBe('2026-01-01')
        ->and($window->valueStart)->toBe(1000.0)
        ->and($window->contributions)->toBe(200.0)
        ->and($window->pnl)->toBe(200.0)
        ->and($window->pct)->toBe(17.6);
});

it('anchors the window start on the last day at or before the boundary', function () {
    $daily = new ValuationSeriesData(
        ['2026-01-01', '2026-01-15', '2026-03-01'],
        [1000.0, 2000.0, 3000.0],
        [1000.0, 1000.0, 1000.0],
        [10.0, 20.0, 30.0],
    );

    // Boundary 2026-02-01 => début pris au 2026-01-15 (valeur 2000) : (3000 - 2000) / 2000 = +50%.
    $window = (new ValuationCalculator)->returnOverWindow($daily, '2026-02-01');

    expect($window->pct)->toBe(50.0)
        ->and($window->startDate)->toBe('2026-01-15')
        ->and($window->valueStart)->toBe(2000.0)
        ->and($window->contributions)->toBe(0.0);
});

it('returns null when the series does not reach the boundary', function () {
    $daily = new ValuationSeriesData(
        ['2026-02-01', '2026-03-01'],
        [1000.0, 1200.0],
        [1000.0, 1000.0],
        [100.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});

it('returns null when the starting value is zero', function () {
    $daily = new ValuationSeriesData(
        ['2026-01-01', '2026-02-01'],
        [0.0, 500.0],
        [0.0, 0.0],
        [0.0, 50.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});

it('builds trailing performances: YTD, monthly, one row per full year, then Max', function () {
    $daily = new ValuationSeriesData(
        ['2023-01-01', '2023-07-01', '2024-07-01', '2025-07-01', '2026-01-01', '2026-04-01', '2026-06-01', '2026-07-01'],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1200.0],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0],
        [100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['YTD', '1M', '3M', '6M', '1Y', '2Y', 'MAX'])
        ->and(array_map(fn ($perf) => $perf->label, $performances))->toBe(['YTD', '1 mois', '3 mois', '6 mois', '1 an', '2 ans', 'Max'])
        ->and($performances[0]->pct)->toBe(20.0)
        ->and($performances[6]->pct)->toBe(20.0)
        ->and($performances[0]->startDate)->toBe('2026-01-01')
        ->and($performances[0]->valueStart)->toBe(1000.0)
        ->and($performances[0]->contributions)->toBe(0.0)
        ->and($performances[0]->gain)->toBe(200.0)
        ->and($performances[6]->startDate)->toBe('2023-01-01');
});

it('starts the Max window on the first day of the series', function () {
    // Trois ans pleins d'historique : la ligne « 3 ans » est remplacée par Max, qui part
    // du 2023-01-01 (valeur 500) au lieu du 2023-07-01 que « 3 ans » aurait pris.
    $daily = new ValuationSeriesData(
        ['2023-01-01', '2023-07-01', '2024-07-01', '2025-07-01', '2026-07-01'],
        [500.0, 1000.0, 1000.0, 1000.0, 1200.0],
        [500.0, 500.0, 500.0, 500.0, 500.0],
        [50.0, 100.0, 100.0, 100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);
    $max = $performances[count($performances) - 1];

    expect($max->key)->toBe('MAX')
        ->and($max->label)->toBe('Max')
        ->and($max->startDate)->toBe('2023-01-01')
        ->and($max->valueStart)->toBe(500.0)
        ->and($max->contributions)->toBe(0.0)
        ->and($max->gain)->toBe(700.0)
        ->and($max->pct)->toBe(140.0);
});

it('omits the periods the series does not cover', function () {
    // Série qui démarre le 2026-05-01 : ni le début d'année, ni 3 mois, ni 6 mois ne sont couverts.
    $daily = new ValuationSeriesData(
        ['2026-05-01', '2026-07-01'],
        [1000.0, 1200.0],
        [1000.0, 1000.0],
        [100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['1M', 'MAX'])
        ->and($performances[0]->startDate)->toBe('2026-05-01')
        ->and($performances[1]->startDate)->toBe('2026-05-01');
});

it('returns no trailing performances for an empty series', function () {
    expect((new ValuationCalculator)->trailingPerformances(ValuationSeriesData::empty()))->toBe([]);
});

it('exposes per-asset market value aligned on the valuation labels (evolution)', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 2, false, 5, 50)],
        [
            new P(1, '2026-01-01', 100), new P(1, '2026-02-01', 120),
            new P(2, '2026-02-01', 50),
        ],
        null,
        ValuationGranularity::Day,
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01']);

    $byName = collect($series->perAsset)->keyBy('name');
    // asset 1: 10@100 → 1000 puis 10@120 → 1200 ; investi 1000/1000
    expect($byName['#1']->value)->toBe([1000.0, 1200.0])
        ->and($byName['#1']->invested)->toBe([1000.0, 1000.0])
        // asset 2 acheté au 2026-02-01 : valeur 0 puis 5@50 = 250 ; investi 0/250
        ->and($byName['#2']->value)->toBe([0.0, 250.0])
        ->and($byName['#2']->invested)->toBe([0.0, 250.0]);
});

it('keeps sum of per-asset value equal to the total valuation (evolution invariant)', function () {
    $calc = new ValuationCalculator;
    $transactions = [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 4, 25)];
    $prices = [new P(1, '2026-01-01', 110), new P(2, '2026-01-01', 30)];

    $series = $calc->evolution($transactions, $prices, null, ValuationGranularity::Day);
    $daily = $calc->calculateDaily($transactions, $prices);

    foreach ($series->labels as $i => $label) {
        $sum = collect($series->perAsset)->sum(fn ($s) => $s->value[$i]);
        expect(round($sum, 2))->toBe($daily->valuations[$i]);
    }
});

it('reporte le dernier cours connu sur les labels sans prix, et retient la dernière valeur d\'un jour agrégé', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-05', 1, false, 10, 100), tx('2026-01-05', 2, false, 2, 10)],
        [
            // l'actif 1 cote tous les jours, l'actif 2 n'a qu'un cours ancien puis plus rien
            new P(1, '2026-01-05', 100), new P(1, '2026-01-06', 110), new P(1, '2026-01-12', 130),
            new P(2, '2026-01-05', 10),
        ],
        null,
        ValuationGranularity::Week,
    );

    // Semaine agrégée sur son dernier jour coté : le 06 pour la première, le 12 pour la seconde.
    expect($series->labels)->toBe(['2026-01-06', '2026-01-12']);

    $byName = collect($series->perAsset)->keyBy('name');
    expect($byName['#1']->value)->toBe([1100.0, 1300.0])
        // cours du 05 reporté sur les deux labels, faute de cotation plus récente
        ->and($byName['#2']->value)->toBe([20.0, 20.0])
        ->and($byName['#2']->invested)->toBe([20.0, 20.0]);
});

it('ne valorise rien avant la première transaction d\'un actif', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-03-01', 2, false, 5, 40)],
        [
            new P(1, '2026-01-01', 100), new P(1, '2026-02-01', 100), new P(1, '2026-03-01', 100),
            // l'actif 2 cote avant d'être détenu : la quantité, nulle, doit annuler ces cours
            new P(2, '2026-01-01', 40), new P(2, '2026-02-01', 40), new P(2, '2026-03-01', 40),
        ],
        null,
        ValuationGranularity::Day,
    );

    $byName = collect($series->perAsset)->keyBy('name');
    expect($byName['#2']->value)->toBe([0.0, 0.0, 200.0])
        ->and($byName['#2']->invested)->toBe([0.0, 0.0, 200.0]);
});

it('returns an empty evolution series without transactions', function () {
    expect((new ValuationCalculator)->evolution([], [], null, ValuationGranularity::Day))
        ->toEqual(EvolutionSeriesData::empty());
});

it('windows the series to an arbitrary number of months', function () {
    $labels = [];
    for ($d = 0; $d < 400; $d++) {
        $labels[] = Carbon::parse('2025-01-01')->addDays($d)->format('Y-m-d');
    }
    $values = array_map(fn (int $i): float => (float) ($i + 1), array_keys($labels));
    $daily = new ValuationSeriesData($labels, $values, $values, $values);

    $windowed = (new ValuationCalculator)->windowAndAggregate($daily, 3, ValuationGranularity::Day);

    $cutoff = Carbon::parse($labels[399])->subMonthsNoOverflow(3)->format('Y-m-d');
    expect($windowed->labels[0])->toBe($cutoff)
        ->and($windowed->labels[count($windowed->labels) - 1])->toBe($labels[399]);
});

it('cuts the evolution timeline at the requested window', function () {
    $evolution = (new ValuationCalculator)->evolution(
        [tx('2025-01-01', 1, false, 10, 100)],
        [new P(1, '2025-01-01', 100), new P(1, '2026-01-01', 120)],
        3,
        ValuationGranularity::Day,
    );

    expect($evolution->labels[0])->toBeGreaterThan('2025-01-01');
});

it('keeps the whole evolution timeline when no window is given', function () {
    $evolution = (new ValuationCalculator)->evolution(
        [tx('2025-01-01', 1, false, 10, 100)],
        [new P(1, '2025-01-01', 100), new P(1, '2026-01-01', 120)],
        null,
        ValuationGranularity::Day,
    );

    expect($evolution->labels[0])->toBe('2025-01-01');
});
