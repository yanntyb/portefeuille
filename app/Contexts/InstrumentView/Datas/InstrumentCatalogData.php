<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class InstrumentCatalogData implements JsonSerializable
{
    /** @param list<CatalogLineData> $lines */
    public function __construct(public array $lines) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['lines' => $this->lines];
    }
}
