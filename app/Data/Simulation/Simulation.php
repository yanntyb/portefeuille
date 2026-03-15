<?php

namespace App\Data\Simulation;

readonly class Simulation
{
    /**
     * @param  list<SimulationObject>  $objects
     * @param  list<SimulationScenario>  $scenarios
     * @param  list<string>  $pipelineNames
     * @param  list<string>  $hiddenFromScenario
     */
    public function __construct(
        public string $nom,
        public array $objects,
        public array $scenarios,
        public array $pipelineNames = ['CDI', 'SASU'],
        public array $hiddenFromScenario = [],
    ) {}

    /**
     * @return array<string, self>
     */
    public static function available(): array
    {
        return collect([
            CdiVsSasuSimulation::build(),
            InvestissementLocatifSimulation::build(),
        ])->keyBy(fn (self $s): string => $s->nom)->all();
    }

    public static function default(): self
    {
        return CdiVsSasuSimulation::build();
    }

    /**
     * @return array{nom: string, objects: list<array>, scenarios: list<array>, pipelineNames: list<string>, hiddenFromScenario: list<string>}
     */
    public function toArray(): array
    {
        return [
            'nom' => $this->nom,
            'objects' => array_map(fn (SimulationObject $o): array => $o->toArray(), $this->objects),
            'scenarios' => array_map(fn (SimulationScenario $s): array => $s->toArray(), $this->scenarios),
            'pipelineNames' => $this->pipelineNames,
            'hiddenFromScenario' => $this->hiddenFromScenario,
        ];
    }

    /**
     * @param  array{nom: string, objects: list<array>, scenarios: list<array>, pipelineNames?: list<string>, hiddenFromScenario?: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nom: $data['nom'],
            objects: array_map(fn (array $o): SimulationObject => SimulationObject::fromArray($o), $data['objects']),
            scenarios: array_map(fn (array $s): SimulationScenario => SimulationScenario::fromArray($s), $data['scenarios']),
            pipelineNames: $data['pipelineNames'] ?? ['CDI', 'SASU'],
            hiddenFromScenario: $data['hiddenFromScenario'] ?? [],
        );
    }
}
