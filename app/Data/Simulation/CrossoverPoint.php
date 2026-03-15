<?php

namespace App\Data\Simulation;

readonly class CrossoverPoint
{
    public function __construct(
        public ?int $index,
        public ?string $label,
    ) {}

    /**
     * @return array{index: ?int, label: ?string}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'label' => $this->label,
        ];
    }

    /**
     * @param  array{index: ?int, label: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: $data['index'] ?? null,
            label: $data['label'] ?? null,
        );
    }
}
