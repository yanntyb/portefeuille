<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class Percentage extends ValueObject
{
    public function __construct(
        public readonly int|float $value,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->value < 0 || $this->value > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100.');
        }
    }

    public function equals(ValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->value === $other->value;
    }
}
