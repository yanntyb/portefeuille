<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;

/**
 * Le corps validé d'une création, en route vers l'action. Les clés arrivent en camelCase comme
 * tout le JSON du dépôt ; c'est ici qu'elles rencontrent les enums du domaine.
 */
readonly class InstrumentInputData
{
    public function __construct(
        public string $name,
        public string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public AssetClass $assetClass,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: (string) $validated['name'],
            ticker: (string) $validated['ticker'],
            isin: $validated['isin'] ?? null,
            type: InstrumentType::from((string) $validated['type']),
            assetClass: AssetClass::from((string) $validated['assetClass']),
        );
    }
}
