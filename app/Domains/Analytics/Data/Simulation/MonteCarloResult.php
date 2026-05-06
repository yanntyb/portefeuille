<?php

namespace App\Domains\Analytics\Data\Simulation;

readonly class MonteCarloResult
{
    /**
     * @param  list<float>  $p10
     * @param  list<float>  $p50
     * @param  list<float>  $p90
     * @param  list<float>  $capitalInvesti
     */
    public function __construct(
        public int $duree,
        public array $p10,
        public array $p50,
        public array $p90,
        public array $capitalInvesti,
    ) {}
}
