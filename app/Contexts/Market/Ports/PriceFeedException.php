<?php

namespace App\Contexts\Market\Ports;

use RuntimeException;
use Throwable;

class PriceFeedException extends RuntimeException
{
    /**
     * @param  string  $reason  the provider error alone, reportable to the operator as-is
     */
    private function __construct(string $message, public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The underlying failure is chained so nothing about the cause is lost.
     */
    public static function fetchFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self("Price feed fetch failed: {$reason}", $reason, $previous);
    }
}
