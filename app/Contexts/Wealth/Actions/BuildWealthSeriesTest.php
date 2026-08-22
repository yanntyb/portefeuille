<?php

use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Infrastructure\RealEstateClass;
use Illuminate\Support\Carbon;

/** Les titres tels que le tableau de bord les recevrait, face au vrai parc immobilier. */
function securitiesAndProperties(ClassSeriesData $securities): void
{
    fakeWealthClasses(
        fakeWealthClass('securities', series: $securities),
        app(RealEstateClass::class),
    );
}

it('rend une série vide quand aucune classe n\'a d\'historique', function () {
    fakeWealthClasses(fakeWealthClass('securities'));

    expect(app(BuildWealthSeries::class)(999)->labels)->toBe([]);
});

it('étend la grille aux labels de l\'immobilier quand un bien précède la première transaction', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /** Les titres ne commencent qu'en août ; le bien a été acquis vingt mois plus tôt. */
    securitiesAndProperties(new ClassSeriesData(
        labels: ['2026-08-03', '2026-08-10'],
        value: [1000.0, 1100.0],
        invested: [900.0, 900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);

    expect($series->labels[0])->toBeLessThan('2026-08-03')
        ->and($series->classes[0]->values[0])->toBe(0.0)
        ->and($series->classes[1]->values[0])->toBe(0.0);
});

it('reporte la dernière valeur des titres sur les labels de l\'immobilier', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    securitiesAndProperties(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $last = count($series->labels) - 1;

    expect($series->classes[0]->values[$last])->toBe(1000.0);
});

it('donne à chaque classe et à l\'investi la longueur de la grille', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    securitiesAndProperties(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $length = count($series->labels);

    expect($series->classes)->toHaveCount(2)
        ->and($series->classes[0]->values)->toHaveCount($length)
        ->and($series->classes[1]->values)->toHaveCount($length)
        ->and($series->invested)->toHaveCount($length);
});

it('somme les mises de toutes les classes sur la grille commune', function () {
    fakeWealthClasses(
        fakeWealthClass('securities', series: new ClassSeriesData(
            labels: ['2026-01-05'],
            value: [1000.0],
            invested: [800.0],
        )),
        fakeWealthClass('crypto', series: new ClassSeriesData(
            labels: ['2026-01-12'],
            value: [500.0],
            invested: [400.0],
        )),
    );

    $series = app(BuildWealthSeries::class)(999);

    /** Le 5, la crypto n'existe pas encore : la mise vaut celle des titres seuls. */
    expect($series->labels)->toBe(['2026-01-05', '2026-01-12'])
        ->and($series->invested)->toBe([800.0, 1200.0]);
});
