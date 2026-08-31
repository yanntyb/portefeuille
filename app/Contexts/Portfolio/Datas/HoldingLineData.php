<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Enums\AccountType;
use JsonSerializable;

readonly class HoldingLineData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $assetName,
        public ?string $ticker,
        public InstrumentType $type,
        public AssetClass $assetClass,
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
        public float $quantity,
        public ?float $avgCost,
        public ?float $lastPrice,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'ticker' => $this->ticker,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'assetClass' => $this->assetClass->value,
            'assetClassLabel' => $this->assetClass->getLabel(),
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
            'quantity' => $this->quantity,
            'avgCost' => $this->avgCost,
            'lastPrice' => $this->lastPrice,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
