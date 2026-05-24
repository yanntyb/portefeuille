<?php

use App\Shared\Abstractions\Port;

class SimplePort implements Port
{
}

it('can implement Port interface', function () {
    $port = new SimplePort();

    expect($port)->toBeInstanceOf(Port::class);
});
