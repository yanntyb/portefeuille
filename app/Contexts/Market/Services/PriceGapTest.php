<?php

use App\Contexts\Market\Services\PriceGap;

it('rend l\'écart d\'un cours à sa référence en pourcentage', function () {
    expect((new PriceGap)->pct(80.0, 100.0))->toBe(25.0);
});

it('rend un écart négatif quand le cours passe sous sa référence', function () {
    expect((new PriceGap)->pct(100.0, 90.0))->toBe(-10.0);
});

it('ne rend rien sans référence ou sans cours', function () {
    expect((new PriceGap)->pct(null, 100.0))->toBeNull();
    expect((new PriceGap)->pct(100.0, null))->toBeNull();
});

it('ne rend rien sur une référence nulle, qui ne divise pas', function () {
    expect((new PriceGap)->pct(0.0, 100.0))->toBeNull();
});
