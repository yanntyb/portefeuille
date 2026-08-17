<?php

it('zoome à la molette sans descendre sous un an ni redemander l\'historique au serveur', function () {
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

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThan(32.0);

    scrollChart($page, 'evolution', -400);

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThanOrEqual($opening);
    expect($page->script('window.__requestsAfterLoad'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});
