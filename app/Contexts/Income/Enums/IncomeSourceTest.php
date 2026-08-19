<?php

use App\Contexts\Income\Enums\IncomeSource;

it('étiquette chaque source en français', function () {
    expect(IncomeSource::Dividend->getLabel())->toBe('Dividendes');
});

it('liste ses valeurs', function () {
    expect(IncomeSource::values())->toBe(['dividend']);
});
