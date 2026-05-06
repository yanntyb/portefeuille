<?php

namespace App\Domains\Analytics\Data\Simulation;

readonly class ScenarioResult
{
    /**
     * @param  array<string, string>  $results
     */
    public function __construct(
        public string $scenario,
        public array $results,
    ) {}

    /**
     * @return array{scenario: string, results: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario,
            'results' => $this->results,
        ];
    }

    /**
     * @param  array{scenario: string, results: array<string, string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scenario: $data['scenario'],
            results: $data['results'],
        );
    }
}
