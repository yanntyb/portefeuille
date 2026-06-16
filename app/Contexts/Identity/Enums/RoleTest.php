<?php

use App\Contexts\Identity\Enums\Role;

it('expose un label français par cas', function () {
    expect(Role::Admin->getLabel())->toBe('Admin')
        ->and(Role::User->getLabel())->toBe('Utilisateur');
});

it('expose une couleur par cas', function () {
    expect(Role::Admin->getColor())->toBe('danger')
        ->and(Role::User->getColor())->toBe('info');
});

it('a deux cas', function () {
    expect(Role::cases())->toHaveCount(2);
});
