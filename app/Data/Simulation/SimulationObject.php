<?php

namespace App\Data\Simulation;

readonly class SimulationObject
{
    /**
     * @param  list<SimulationStep>  $steps
     */
    public function __construct(
        public string $nom,
        public SimulationValue $value,
        public ?string $pipeline,
        public array $steps,
    ) {}

    /**
     * @return array{nom: string, value: string, pipeline: ?string, steps: list<array{label: string, type: string}>}
     */
    public function toArray(): array
    {
        return [
            'nom' => $this->nom,
            'value' => $this->value->formatted(),
            'pipeline' => $this->pipeline,
            'steps' => array_map(fn (SimulationStep $step): array => $step->toArray(), $this->steps),
        ];
    }

    /**
     * @param  array{nom: string, value: string, pipeline: ?string, steps: list<array{label: string, type: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nom: $data['nom'],
            value: SimulationValue::parse($data['value']),
            pipeline: $data['pipeline'] ?? null,
            steps: array_map(fn (array $step): SimulationStep => SimulationStep::fromArray($step), $data['steps'] ?? []),
        );
    }

    public function withValue(SimulationValue $value): self
    {
        return new self(
            nom: $this->nom,
            value: $value,
            pipeline: $this->pipeline,
            steps: $this->steps,
        );
    }
}
