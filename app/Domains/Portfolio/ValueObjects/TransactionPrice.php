<?php

namespace App\Domains\Portfolio\ValueObjects;

use InvalidArgumentException;

class TransactionPrice
{
    public function __construct(public readonly float $value)
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Price must be >= 0');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function equals(TransactionPrice $other): bool
    {
        return $this->value === $other->value;
    }

    public function multiply(float $quantity): float
    {
        return $this->value * $quantity;
    }
}
