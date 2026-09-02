<?php

use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;
use Inertia\DeferProp;
use Inertia\Response;

it('rend les props synchrones telles quelles et enveloppe chaque différée dans son groupe', function () {
    $page = new PageProps(
        sync: ['titre' => 'Enveloppe'],
        deferred: ['positions' => new DeferredProp(fn (): array => [1, 2], 'positions')],
    );

    $props = $page->toInertiaProps();

    expect($props['titre'])->toBe('Enveloppe')
        ->and($props['positions'])->toBeInstanceOf(DeferProp::class)
        ->and($props['positions']->group())->toBe('positions')
        ->and(($props['positions'])())->toBe([1, 2]);
});

it('résout toutes les différées en une seule passe, dans l\'ordre sync puis différé', function () {
    $appels = 0;
    $page = new PageProps(
        sync: ['a' => 1],
        deferred: [
            'b' => new DeferredProp(function () use (&$appels): int {
                $appels++;

                return 2;
            }, 'groupe'),
            'c' => new DeferredProp(fn (): array => [], 'groupe'),
        ],
    );

    expect($page->resolve())->toBe(['a' => 1, 'b' => 2, 'c' => []])
        ->and($appels)->toBe(1);
});

it('rend une réponse Inertia sur le composant demandé', function () {
    $page = new PageProps(sync: ['a' => 1], deferred: []);

    expect($page->render('Wallet/Show'))->toBeInstanceOf(Response::class);
});
