<?php

namespace App\Domains\AssetView\DTOs;

use App\Domains\Asset\Models\AssetPrice;

readonly class PriceHistoryDTO
{
    public function __construct(
        public string $date,
        public float $close,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?int $volume = null,
    ) {}

    public static function fromModel(AssetPrice $model): self
    {
        return new self(
            date: $model->date->format('Y-m-d'),
            close: (float) $model->close,
            open: $model->open !== null ? (float) $model->open : null,
            high: $model->high !== null ? (float) $model->high : null,
            low: $model->low !== null ? (float) $model->low : null,
            volume: $model->volume,
        );
    }
}
