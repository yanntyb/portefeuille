<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use Carbon\Carbon;

class DateTime extends ValueObject
{
    public readonly Carbon $value;

    public function __construct(Carbon|string $value)
    {
        $this->value = $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    public static function now(): self
    {
        return new self(Carbon::now());
    }

    public function equals(ValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->value->eq($other->value);
    }
}
