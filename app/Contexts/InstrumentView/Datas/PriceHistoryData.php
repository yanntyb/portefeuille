<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class PriceHistoryData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $close
     */
    public function __construct(
        public array $labels,
        public array $close,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'close' => $this->close,
        ];
    }
}
