<?php

namespace App\Domains\Analytics\Data\Simulation;

readonly class SimulationScenario
{
    /**
     * @param  list<ScenarioOverride>  $overrides
     */
    public function __construct(
        public string $nom,
        public array $overrides,
    ) {}

    /**
     * @return array{nom: string, overrides: list<array{param: string, operator: string, value: string}>}
     */
    public function toArray(): array
    {
        return [
            'nom' => $this->nom,
            'overrides' => array_map(fn (ScenarioOverride $o): array => $o->toArray(), $this->overrides),
        ];
    }

    /**
     * @param  array{nom: string, overrides: list<array{param: string, operator: string, value: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nom: $data['nom'],
            overrides: array_map(fn (array $o): ScenarioOverride => ScenarioOverride::fromArray($o), $data['overrides'] ?? []),
        );
    }
}
