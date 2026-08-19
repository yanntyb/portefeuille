<?php

use App\Contexts\Market\Datas\DividendData;

it('se construit depuis la charge utile du script', function () {
    $data = DividendData::fromArray(['ex_date' => '2026-03-05', 'amount_per_share' => 0.51]);

    expect($data->exDate)->toBe('2026-03-05')
        ->and($data->amountPerShare)->toBe(0.51);
});
