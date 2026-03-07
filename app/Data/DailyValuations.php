<?php

namespace App\Data;

readonly class DailyValuations
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $valuations
     * @param  list<float>  $invested
     * @param  list<float>  $fees
     */
    public function __construct(
        public array $labels,
        public array $valuations,
        public array $invested,
        public array $fees,
    ) {}
}
