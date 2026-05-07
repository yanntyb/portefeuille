<?php

namespace Tests\Unit\Domains\Portfolio\ValueObjects;

use App\Domains\Portfolio\ValueObjects\TransactionPrice;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TransactionPriceTest extends TestCase
{
    public function test_creates_valid_price(): void
    {
        $price = new TransactionPrice(123.4567);

        $this->assertEquals(123.4567, $price->value);
    }

    public function test_creates_price_with_decimals(): void
    {
        $price = new TransactionPrice(100.1234);

        $this->assertEquals(100.1234, $price->value);
    }

    public function test_creates_price_with_zero(): void
    {
        $price = new TransactionPrice(0);

        $this->assertEquals(0, $price->value);
    }

    public function test_rejects_negative_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be >= 0');

        new TransactionPrice(-1.5);
    }

    public function test_casts_to_string(): void
    {
        $price = new TransactionPrice(123.4567);

        $this->assertEquals('123.4567', (string) $price);
    }

    public function test_equals_another_price(): void
    {
        $price1 = new TransactionPrice(123.45);
        $price2 = new TransactionPrice(123.45);
        $price3 = new TransactionPrice(123.46);

        $this->assertTrue($price1->equals($price2));
        $this->assertFalse($price1->equals($price3));
    }

    public function test_multiplies_with_quantity(): void
    {
        $price = new TransactionPrice(100.5);

        $total = $price->multiply(10);

        $this->assertEquals(1005.0, $total);
    }
}
