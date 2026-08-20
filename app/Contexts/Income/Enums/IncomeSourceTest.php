<?php

use App\Contexts\Income\Enums\IncomeSource;

it('étiquette chaque source en français', function () {
    expect(IncomeSource::Dividend->getLabel())->toBe('Dividendes')
        ->and(IncomeSource::Rent->getLabel())->toBe('Loyers');
});

it('liste ses valeurs', function () {
    expect(IncomeSource::values())->toBe(['dividend', 'rent']);
});
