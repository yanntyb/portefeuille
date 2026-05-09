<?php

namespace App\Domains\Asset\ValueObjects;

readonly class PriceData
{
    public function __construct(
        public string $date,
        public float $close,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?int $volume = null,
    ) {}

    /**
     * Create from adapter response array.
     *
     * @param  array{date: string, close: float, open?: float, high?: float, low?: float, volume?: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            close: $data['close'],
            open: $data['open'] ?? null,
            high: $data['high'] ?? null,
            low: $data['low'] ?? null,
            volume: $data['volume'] ?? null,
        );
    }
}
