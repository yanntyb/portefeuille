<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\AccountType;
use JsonSerializable;

/**
 * Une enveloppe de détention et ce qu'elle tient. Les règles qu'elle affiche sont déclaratives :
 * elles viennent de `AccountType`, aucune n'entre dans un calcul.
 *
 * `gainPct` rend `null`, jamais `0.0`, quand le coût est nul — la règle du contexte, ici comme sur
 * la ligne et sur le total.
 *
 * `ageInYears` est `null` quand l'enveloppe n'a pas de date d'ouverture connue : une ancienneté
 * fausse serait pire qu'absente.
 *
 * `cashBalance` est le compte espèces réel de l'enveloppe, lu à aujourd'hui — jamais `null` : une
 * enveloppe sans aucun mouvement affiche `0`, pas un tiret.
 *
 * `cost` est le coût de revient des positions du compte, `realizedGain` le gain déjà encaissé par
 * ses ventes — lu sur les transactions, un actif soldé n'ayant plus de position à totaliser. Les
 * deux se lisent l'un sous l'autre avec `gain`, qui est le gain latent : ordre et vocabulaire de
 * `PortfolioOverviewData`, pour que les deux échelles se lisent pareil.
 */
readonly class AccountLineData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames actifs que l'enveloppe n'admet pas */
    public function __construct(
        public int $walletId,
        public string $walletName,
        /** Établissement qui tient le compte ; `null` quand il n'est pas renseigné. */
        public ?string $broker,
        public AccountType $accountType,
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
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
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
