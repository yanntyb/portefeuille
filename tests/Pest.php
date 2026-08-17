<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser', '../app/Contexts');

pest()->extend(Tests\TestCase::class)
    ->in('../app/Shared');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Borne basse de l'axe des valeurs d'un graphe. ECharts peint ses libellés en SVG sans les
 * nommer : les seuls alignés à droite sont ceux de l'axe des valeurs.
 */
function lowestValueAxisLabel(Pest\Browser\Api\PendingAwaitablePage $page, string $section): float
{
    return (float) $page->script("(() => {
        const labels = [...document.querySelectorAll('[data-section={$section}] [data-chart] svg text')]
            .filter((text) => text.getAttribute('text-anchor') === 'end')
            .map((text) => parseFloat(text.textContent.replace(/[^0-9,.-]/g, '').replace(',', '.')))
            .filter((value) => !Number.isNaN(value));

        return labels.length === 0 ? -1 : Math.min(...labels);
    })()");
}

/** Amplitude de la fenêtre visible, en pourcentage de l'historique, publiée par le graphe en attribut. */
function zoomWindowSpan(Pest\Browser\Api\PendingAwaitablePage $page, string $section): float
{
    $window = (string) $page->script("document.querySelector('[data-section={$section}] [data-chart]').getAttribute('data-zoom-window')");
    [$start, $end] = array_map('floatval', explode('-', $window));

    return $end - $start;
}

/** Molette sur le graphe : vers l'avant on zoome, vers l'arrière on dézoome. */
function scrollChart(Pest\Browser\Api\PendingAwaitablePage $page, string $section, int $deltaY): void
{
    $page->script("(() => {
        const chart = document.querySelector('[data-section={$section}] [data-chart]');
        const box = chart.getBoundingClientRect();
        chart.querySelector('svg').dispatchEvent(new WheelEvent('wheel', {
            deltaY: {$deltaY},
            clientX: box.left + box.width / 2,
            clientY: box.top + box.height / 3,
            bubbles: true,
            cancelable: true,
        }));
    })()");
}
