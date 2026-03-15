<?php

namespace App\Data\Simulation;

readonly class SimulationStep
{
    public function __construct(
        public string $label,
        public string $type,
    ) {}

    /**
     * @return array{label: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'type' => $this->type,
        ];
    }

    /**
     * @param  array{label: string, type: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            type: $data['type'],
        );
    }
}
