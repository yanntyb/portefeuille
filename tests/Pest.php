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

/**
 * Courbes Valeur et Investi tracées dans le graphe d'une section, sous la forme « 1|0 ».
 * Les deux se distinguent par leur teinte, l'investi ajoutant des pointillés.
 */
function drawnLines(Pest\Browser\Api\PendingAwaitablePage $page, string $section): string
{
    return (string) $page->script("(() => {
        const paths = document.querySelectorAll('[data-section={$section}] [data-chart] svg path');
        const value = [...paths].filter((path) => path.getAttribute('stroke') === '#4f46e5').length;
        const invested = [...paths]
            .filter((path) => path.getAttribute('stroke') === '#94a3b8' && path.hasAttribute('stroke-dasharray'))
            .length;

        return [value, invested].join('|');
    })()");
}
