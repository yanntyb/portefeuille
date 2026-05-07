<?php

namespace App\Domains\Portfolio\ValueObjects;

use InvalidArgumentException;

class QuantityValue
{
    public function __construct(public readonly float $value)
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Quantity must be >= 0');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function equals(QuantityValue $other): bool
    {
        return $this->value === $other->value;
    }
}
