<?php

use App\Contexts\Identity\Enums\Role;

it('exposes a label per case', function () {
    expect(Role::Admin->getLabel())->toBe('Admin')
        ->and(Role::User->getLabel())->toBe('Utilisateur');
});

it('exposes a color per case', function () {
    expect(Role::Admin->getColor())->toBe('danger')
        ->and(Role::User->getColor())->toBe('info');
});
