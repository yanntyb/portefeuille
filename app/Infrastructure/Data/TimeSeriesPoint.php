<?php

namespace App\Infrastructure\Data;

readonly class TimeSeriesPoint
{
    public function __construct(
        public string $date,
        public float $value,
    ) {}
}
