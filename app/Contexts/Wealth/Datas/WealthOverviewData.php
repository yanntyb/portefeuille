<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Le patrimoine d'un utilisateur : son total, et ses deux classes d'actif. */
readonly class WealthOverviewData implements JsonSerializable
{
    public function __construct(
        public float $totalValue,
        public float $totalInvested,
        public float $totalGain,
        public ?float $totalGainPct,
        public AssetClassData $securities,
        public AssetClassData $realEstate,
    ) {}

    public static function empty(): self
    {
        $empty = AssetClassData::from(ClassSnapshotData::empty());

        return new self(0.0, 0.0, 0.0, null, $empty, $empty);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalInvested' => $this->totalInvested,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'securities' => $this->securities->jsonSerialize(),
            'realEstate' => $this->realEstate->jsonSerialize(),
        ];
    }
}
