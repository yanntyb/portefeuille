<?php

namespace App\Contexts\PortfolioView\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

readonly class InstrumentDetailData implements JsonSerializable
{
    /**
     * @param  list<TransactionLineData>  $transactions
     * @param  list<SectorWeightData>  $sectors
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public AssetClass $assetClass,
        public ?float $lastPrice,
        public ?string $lastPriceDate,
        public ?PositionData $position,
        public array $transactions,
        public array $sectors,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'assetClass' => $this->assetClass->value,
            'assetClassLabel' => $this->assetClass->getLabel(),
            'assetClassHref' => '/'.$this->assetClass->slug(),
            'lastPrice' => $this->lastPrice,
            'lastPriceDate' => $this->lastPriceDate,
            'position' => $this->position,
            'transactions' => $this->transactions,
            'sectors' => $this->sectors,
        ];
    }
}
