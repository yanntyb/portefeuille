<?php

namespace App\Data;

readonly class CumulativeData
{
    /**
     * @param  array<int, list<TimeSeriesPoint>>  $quantities
     * @param  list<TimeSeriesPoint>  $invested
     * @param  list<TimeSeriesPoint>  $fees
     */
    public function __construct(
        public array $quantities,
        public array $invested,
        public array $fees,
    ) {}
}
