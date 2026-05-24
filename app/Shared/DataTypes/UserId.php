<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class UserId extends ValueObject
{
    public function __construct(
        public readonly int $value,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->value <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
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
