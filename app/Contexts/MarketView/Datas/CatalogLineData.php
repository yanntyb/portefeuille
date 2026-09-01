<?php

namespace App\Contexts\MarketView\Datas;

use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

/**
 * Une ligne du catalogue d'une exposition : un instrument connu du marché, détenu ou non.
 *
 * `typeLabel` se dérive de l'enum au moment de sérialiser plutôt que d'être recopié à la
 * construction — sinon le libellé cesserait de suivre l'enum le jour où celui-ci change.
 *
 * Les clés jumellent l'interface `CatalogLine` de `resources/js/lib/catalog.ts` : un instrument
 * jamais acheté porte `held` à faux, et ni quantité ni valorisation.
 */
readonly class CatalogLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public ?float $lastPrice,
        public bool $held,
        public ?float $quantity,
        public ?float $marketValue,
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
            'lastPrice' => $this->lastPrice,
            'held' => $this->held,
            'quantity' => $this->quantity,
            'marketValue' => $this->marketValue,
        ];
    }
}
