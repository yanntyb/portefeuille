<?php

namespace App\Data\Simulation;

readonly class ScenarioOverride
{
    public function __construct(
        public string $param,
        public string $operator,
        public string $value,
    ) {}

    /**
     * @return array{param: string, operator: string, value: string}
     */
    public function toArray(): array
    {
        return [
            'param' => $this->param,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }

    /**
     * @param  array{param: string, operator: string, value: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            param: $data['param'],
            operator: $data['operator'],
            value: $data['value'],
        );
    }
}
