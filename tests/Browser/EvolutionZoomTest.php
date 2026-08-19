<?php

it('laisse la molette à la page : le tracé ne se zoome plus sous le curseur', function () {
    ['user' => $user] = denseHistoryFixture();

    $this->actingAs($user);

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $page->script('(() => {
        window.__requestsAfterLoad = 0;
        const open = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (...args) {
            window.__requestsAfterLoad += 1;
            return open.apply(this, args);
        };
        const fetched = window.fetch;
        window.fetch = function (...args) {
            window.__requestsAfterLoad += 1;
            return fetched.apply(this, args);
        };
    })()');

    $opening = zoomWindowSpan($page, 'evolution');

    foreach (range(1, 3) as $ignored) {
        scrollChart($page, 'evolution', 400);
    }

    expect(zoomWindowSpan($page, 'evolution'))->toBe($opening);

    foreach (range(1, 3) as $ignored) {
        scrollChart($page, 'evolution', -400);
    }

    expect(zoomWindowSpan($page, 'evolution'))->toBe($opening);
    expect($page->script('window.__requestsAfterLoad'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});

it('ouvre malgré tout sur les douze derniers mois, la mini-timeline restant la commande de zoom', function () {
    ['user' => $user] = denseHistoryFixture();

    $this->actingAs($user);

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    // Trois ans d'historique, un an montré : la fenêtre couvre environ un tiers de la durée.
    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThan(30.0)->toBeLessThan(36.0);

    $page->assertNoJavaScriptErrors();
});
