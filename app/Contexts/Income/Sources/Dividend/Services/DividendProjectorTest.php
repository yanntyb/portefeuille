<?php

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Services\DividendProjector;
use Illuminate\Support\Carbon;

function detached(string $date, float $amountPerShare, int $assetId = 1): DividendRecordData
{
    return new DividendRecordData($assetId, Carbon::parse($date), $amountPerShare);
}

/** La borne des douze mois, telle que les actions la fixent : minuit, un an en arrière. */
function twelveMonthsBefore(string $today): Carbon
{
    return Carbon::parse($today)->subYear()->startOfDay();
}

it('annualise les détachements des douze derniers mois sur la quantité détenue', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2026-05-20', 5.5), detached('2025-12-10', 7.5)],
        [1 => 10.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([1 => 130.0]);
});

it('ignore un détachement antérieur à la fenêtre', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2025-05-20', 5.5), detached('2026-05-20', 3.0)],
        [1 => 10.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([1 => 30.0]);
});

it('compte un détachement tombant exactement sur la borne', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2025-08-19', 2.0)],
        [1 => 10.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([1 => 20.0]);
});

it('laisse de côté un actif dont la position est soldée', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2026-05-20', 5.5)],
        [1 => 0.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([]);
});

it('laisse de côté un actif détenu mais sans détachement récent', function () {
    // Un émetteur qui a coupé son dividende ne projette rien : la fenêtre glissante est vide.
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2024-05-20', 5.5)],
        [1 => 10.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([]);
});

it('ne mélange pas les actifs', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2026-05-20', 5.5, assetId: 1), detached('2026-06-10', 1.0, assetId: 2)],
        [1 => 10.0, 2 => 100.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([1 => 55.0, 2 => 100.0]);
});

it('ignore un détachement sur un actif que l\'utilisateur ne détient plus du tout', function () {
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2026-05-20', 5.5, assetId: 2)],
        [1 => 10.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([]);
});

it('arrondit l\'estimation au centime', function () {
    // 3 × 0.125 = 0.375 : round() rend 0,38, une troncature rendrait 0,37.
    $estimates = (new DividendProjector)->annualEstimates(
        [detached('2026-05-20', 0.125)],
        [1 => 3.0],
        twelveMonthsBefore('2026-08-19'),
    );

    expect($estimates)->toBe([1 => 0.38]);
});

it('rend un tableau vide sans détachement', function () {
    expect((new DividendProjector)->annualEstimates([], [1 => 10.0], twelveMonthsBefore('2026-08-19')))->toBe([]);
});
