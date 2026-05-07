<?php

namespace Tests\Unit\Domains\Portfolio\ValueObjects;

use App\Domains\Portfolio\ValueObjects\QuantityValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QuantityValueTest extends TestCase
{
    public function test_creates_valid_quantity(): void
    {
        $quantity = new QuantityValue(10.5);

        $this->assertEquals(10.5, $quantity->value);
    }

    public function test_creates_quantity_with_decimal(): void
    {
        $quantity = new QuantityValue(10.1234);

        $this->assertEquals(10.1234, $quantity->value);
    }

    public function test_creates_quantity_with_zero(): void
    {
        $quantity = new QuantityValue(0);

        $this->assertEquals(0, $quantity->value);
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be >= 0');

        new QuantityValue(-1);
    }

    public function test_casts_to_string(): void
    {
        $quantity = new QuantityValue(10.5);

        $this->assertEquals('10.5', (string) $quantity);
    }

    public function test_equals_another_quantity(): void
    {
        $quantity1 = new QuantityValue(10.5);
        $quantity2 = new QuantityValue(10.5);
        $quantity3 = new QuantityValue(10.6);

        $this->assertTrue($quantity1->equals($quantity2));
        $this->assertFalse($quantity1->equals($quantity3));
    }
}
