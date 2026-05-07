<?php

namespace App\Domains\Portfolio\ValueObjects;

use InvalidArgumentException;

class FeesValue
{
    public function __construct(public readonly float $value)
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Fees must be >= 0');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function equals(FeesValue $other): bool
    {
        return $this->value === $other->value;
    }
}
