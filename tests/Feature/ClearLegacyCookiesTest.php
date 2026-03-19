<?php

use function Pest\Laravel\call;

test('it expires the legacy laravel-session cookie when present', function () {
    $response = call('GET', '/', cookies: ['laravel-session' => 'old-value']);

    $cookies = collect($response->headers->getCookies());
    $legacyCookie = $cookies->first(fn ($c) => $c->getName() === 'laravel-session');

    expect($legacyCookie)->not->toBeNull();
    expect($legacyCookie->isCleared())->toBeTrue();
});
