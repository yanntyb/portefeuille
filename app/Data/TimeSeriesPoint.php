<?php

namespace App\Data;

readonly class TimeSeriesPoint
{
    public function __construct(
        public string $date,
        public float $value,
    ) {}
}
