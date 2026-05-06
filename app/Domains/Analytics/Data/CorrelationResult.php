<?php

namespace App\Domains\Analytics\Data;

readonly class CorrelationResult
{
    /**
     * @param  array<int, array<int, float>>  $matrix  NxN correlation matrix
     * @param  list<string>  $labels  Security names
     * @param  float  $average  Average correlation (upper triangle)
     */
    public function __construct(
        public array $matrix,
        public array $labels,
        public float $average,
    ) {}
}
