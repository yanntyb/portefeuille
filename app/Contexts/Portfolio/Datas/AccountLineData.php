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
 */
readonly class AccountLineData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames actifs que l'enveloppe n'admet pas */
    public function __construct(
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
        public float $marketValue,
        public float $gain,
        public ?float $gainPct,
        public ?int $ageInYears,
        public ?int $maturityYears,
        public string $taxRegimeLabel,
        public array $ineligibleAssetNames,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'ageInYears' => $this->ageInYears,
            'maturityYears' => $this->maturityYears,
            'taxRegimeLabel' => $this->taxRegimeLabel,
            'ineligibleAssetNames' => $this->ineligibleAssetNames,
        ];
    }
}
