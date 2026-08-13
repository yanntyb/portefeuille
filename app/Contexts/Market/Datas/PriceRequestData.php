<?php

namespace App\Contexts\Market\Datas;

/**
 * A price fetch request for one ticker over an inclusive date window.
 *
 * Translating the window to a provider's own convention belongs to the adapter.
 */
readonly class PriceRequestData
{
    public function __construct(
        public string $ticker,
        public string $startDate,
        public string $endDate,
    ) {}
}
