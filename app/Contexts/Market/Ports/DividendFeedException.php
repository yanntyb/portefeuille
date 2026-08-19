<?php

namespace App\Contexts\Market\Ports;

use RuntimeException;
use Throwable;

class DividendFeedException extends RuntimeException
{
    /**
     * @param  string  $reason  l'erreur du fournisseur seule, rapportable telle quelle à l'opérateur
     */
    private function __construct(string $message, public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * L'échec sous-jacent est chaîné pour que rien de la cause ne soit perdu.
     */
    public static function fetchFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self("Dividend feed fetch failed: {$reason}", $reason, $previous);
    }
}
