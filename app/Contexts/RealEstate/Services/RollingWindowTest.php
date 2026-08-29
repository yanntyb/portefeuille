<?php

use App\Contexts\RealEstate\Services\RollingWindow;
use Illuminate\Support\Carbon;

it('remonte au premier jour du mois d\'il y a onze mois', function () {
    expect((new RollingWindow)->monthsFull(Carbon::parse('2026-08-29 15:30:00')))->toBe('2025-09-01');
});

it('ne déborde pas sur un mois plus court', function () {
    expect((new RollingWindow)->monthsFull(Carbon::parse('2026-03-31')))->toBe('2025-04-01');
});
