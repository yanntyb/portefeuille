<?php

use App\Contexts\Identity\Models\User;

it('ne sème aucun utilisateur sur une base fraîchement migrée', function () {
    expect(User::query()->count())->toBe(0);
});
