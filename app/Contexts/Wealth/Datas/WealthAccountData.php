<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Une enveloppe de détention vue du patrimoine : jumelle de `Portfolio\Datas\AccountLineData`,
 * dont elle reproduit le JSON clé pour clé — l'instantané hors-ligne publie
 * `sha1(json_encode($body))`, qu'un ordre différent ferait retélécharger à tous les clients.
 *
 * Les libellés arrivent déjà rendus : le patrimoine ne connaît pas `AccountType`, c'est le
 * travail de l'adaptateur de le traduire.
 *
 * `cashBalance` est le compte espèces réel de l'enveloppe, jamais `null` : une enveloppe sans
 * mouvement affiche `0`, pas un tiret.
 */
readonly class WealthAccountData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames */
    public function __construct(
        public int $walletId,
        public string $walletName,
        /** Établissement qui tient le compte ; `null` quand il n'est pas renseigné. */
        public ?string $broker,
        public string $accountType,
        public string $accountTypeLabel,
        public float $marketValue,
        public float $gain,
        public ?float $gainPct,
        public ?int $ageInYears,
        public ?int $maturityYears,
        public string $taxRegimeLabel,
        public array $ineligibleAssetNames,
        public float $cashBalance,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'broker' => $this->broker,
            'accountType' => $this->accountType,
            'accountTypeLabel' => $this->accountTypeLabel,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'ageInYears' => $this->ageInYears,
            'maturityYears' => $this->maturityYears,
            'taxRegimeLabel' => $this->taxRegimeLabel,
            'ineligibleAssetNames' => $this->ineligibleAssetNames,
            'cashBalance' => $this->cashBalance,
        ];
    }
}
