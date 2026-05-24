<?php

use App\Shared\Abstractions\ValueObject;

class SimpleValueObject extends ValueObject
{
    public function __construct(
        public readonly string $value,
    ) {
    }

    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->value === $other->value;
    }
}

it('checks equality for value objects with same values', function () {
    $vo1 = new SimpleValueObject('test');
    $vo2 = new SimpleValueObject('test');

    expect($vo1->equals($vo2))->toBeTrue();
});

it('checks inequality for value objects with different values', function () {
    $vo1 = new SimpleValueObject('test');
    $vo2 = new SimpleValueObject('different');

    expect($vo1->equals($vo2))->toBeFalse();
});
