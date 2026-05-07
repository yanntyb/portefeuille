<?php

namespace Tests\Unit\Domains\Portfolio\ValueObjects;

use App\Domains\Portfolio\ValueObjects\FeesValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FeesValueTest extends TestCase
{
    public function test_creates_valid_fees(): void
    {
        $fees = new FeesValue(12.50);

        $this->assertEquals(12.50, $fees->value);
    }

    public function test_creates_fees_with_decimals(): void
    {
        $fees = new FeesValue(12.99);

        $this->assertEquals(12.99, $fees->value);
    }

    public function test_creates_fees_with_zero(): void
    {
        $fees = new FeesValue(0);

        $this->assertEquals(0, $fees->value);
    }

    public function test_rejects_negative_fees(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fees must be >= 0');

        new FeesValue(-1);
    }

    public function test_casts_to_string(): void
    {
        $fees = new FeesValue(12.50);

        $this->assertEquals('12.5', (string) $fees);
    }

    public function test_equals_another_fees(): void
    {
        $fees1 = new FeesValue(12.50);
        $fees2 = new FeesValue(12.50);
        $fees3 = new FeesValue(12.51);

        $this->assertTrue($fees1->equals($fees2));
        $this->assertFalse($fees1->equals($fees3));
    }
}
