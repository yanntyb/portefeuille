<?php

namespace App\Exceptions;

use RuntimeException;

class TickerResolutionException extends RuntimeException
{
    public static function noResultForIsin(string $isin): self
    {
        return new self("No ticker found for ISIN: {$isin}");
    }
}
