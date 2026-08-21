<?php

use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use Illuminate\Support\Carbon;

function fakeSecuritiesSeries(ClassSeriesData $series): void
{
    app()->bind(SecuritiesSeriesPort::class, fn (): SecuritiesSeriesPort => new class($series) implements SecuritiesSeriesPort
    {
        public function __construct(private ClassSeriesData $series) {}

        public function seriesFor(int $userId): ClassSeriesData
        {
            return $this->series;
        }
    });
}

it('rend une série vide quand ni les titres ni l\'immobilier n\'ont d\'historique', function () {
    fakeSecuritiesSeries(ClassSeriesData::empty());

    $series = app(BuildWealthSeries::class)(999);

    expect($series->labels)->toBe([]);
});

it('étend la grille aux labels de l\'immobilier quand un bien précède la première transaction', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /** Les titres ne commencent qu'en août ; le bien a été acquis vingt mois plus tôt. */
    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03', '2026-08-10'],
        value: [1000.0, 1100.0],
        invested: [900.0, 900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);

    expect($series->labels[0])->toBeLessThan('2026-08-03')
        ->and($series->securities[0])->toBe(0.0)
        ->and($series->realEstate[0])->toBe(0.0);
});

it('reporte la dernière valeur des titres sur les labels de l\'immobilier', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $last = count($series->labels) - 1;

    expect($series->securities[$last])->toBe(1000.0);
});

it('donne aux trois séries la longueur de la grille', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $length = count($series->labels);

    expect($series->securities)->toHaveCount($length)
        ->and($series->realEstate)->toHaveCount($length)
        ->and($series->invested)->toHaveCount($length);
});
