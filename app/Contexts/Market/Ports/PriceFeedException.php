<?php

namespace App\Contexts\Market\Ports;

use RuntimeException;

class PriceFeedException extends RuntimeException
{
    public static function fetchFailed(string $error): self
    {
        return new self("Price feed fetch failed: {$error}");
    }
}
