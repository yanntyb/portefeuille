<?php

namespace App\Contexts\Market\Datas;

use Carbon\Carbon;

readonly class AssetPriceData
{
    public function __construct(
        public int $assetId,
        public string $date,
        public string $open,
        public string $high,
        public string $low,
        public string $close,
        public int $volume,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    /**
     * Create from PriceData for persistence.
     */
    public static function fromPriceData(int $assetId, PriceData $priceData): self
    {
        $close = (string) $priceData->close;

        return new self(
            assetId: $assetId,
            date: $priceData->date,
            open: (string) ($priceData->open ?? $priceData->close),
            high: (string) ($priceData->high ?? $priceData->close),
            low: (string) ($priceData->low ?? $priceData->close),
            close: $close,
            volume: $priceData->volume ?? 0,
            createdAt: now(),
            updatedAt: now(),
        );
    }

    /**
     * Convert to database attributes array.
     *
     * @return array{asset_id: int, date: string, open: string, high: string, low: string, close: string, volume: int, created_at: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'date' => $this->date,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
        ];
    }
}
