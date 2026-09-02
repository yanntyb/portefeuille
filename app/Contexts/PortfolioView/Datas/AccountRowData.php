<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * L'en-tête d'une enveloppe : jumelle de `Portfolio\Datas\AccountLineData`, dont elle reproduit le
 * JSON clé pour clé — et jumelle aussi de `Wealth\Datas\WealthAccountData`, que le tableau de bord
 * sert pour ses cartes repliées. Les trois doivent bouger ensemble : le front lit un seul type
 * `WealthAccount`, et l'instantané hors-ligne publie `sha1(json_encode($body))`, qu'un ordre
 * différent ferait retélécharger à tous les clients.
 *
 * Les libellés arrivent déjà rendus : la page ne connaît pas `AccountType`, c'est le
 * travail de l'adaptateur de le traduire.
 *
 * `cashBalance` est le compte espèces réel de l'enveloppe, jamais `null` : une enveloppe sans
 * mouvement affiche `0`, pas un tiret.
 */
readonly class AccountRowData implements JsonSerializable
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
        public float $cost,
        public float $gain,
        public ?float $gainPct,
        public float $realizedGain,
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
            'cost' => $this->cost,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'realizedGain' => $this->realizedGain,
            'ageInYears' => $this->ageInYears,
            'maturityYears' => $this->maturityYears,
            'taxRegimeLabel' => $this->taxRegimeLabel,
            'ineligibleAssetNames' => $this->ineligibleAssetNames,
            'cashBalance' => $this->cashBalance,
        ];
    }
}
