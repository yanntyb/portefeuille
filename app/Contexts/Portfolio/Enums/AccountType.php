<?php

namespace App\Contexts\Portfolio\Enums;

use App\Contexts\Market\Enums\AssetClass;

/**
 * L'enveloppe de détention : le compte sur lequel une position est tenue. `AssetClass` dit à quoi
 * le porteur est exposé, cet enum dit sous quel régime il la détient — un même ETF vaut la même
 * chose dans un PEA et dans un compte-titres, il ne s'impose pas pareil.
 *
 * Les règles vivent ici et nulle part ailleurs : ce sont des constantes légales, pas de la
 * configuration. Elles sont déclarées et affichées, jamais appliquées à un calcul — l'application
 * n'estime aucun impôt.
 *
 * Le plafond de versement en est délibérément absent : `TransactionType` n'a pas de mouvement
 * d'espèces, aucun montant versé n'est donc calculable, et un plafond sans son solde ne renseigne
 * sur rien.
 */
enum AccountType: string
{
    case Pea = 'pea';
    case Cto = 'cto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pea => 'PEA',
            self::Cto => 'Compte-titres',
        };
    }

    /** Texte informatif : ce que dit la loi, jamais ce que l'application calcule. */
    public function taxRegimeLabel(): string
    {
        return match ($this) {
            self::Pea => 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
            self::Cto => 'Flat tax 30 %',
        };
    }

    /** Années de détention avant le régime favorable ; null quand l'enveloppe n'en a pas. */
    public function maturityYears(): ?int
    {
        return match ($this) {
            self::Pea => 5,
            self::Cto => null,
        };
    }

    /**
     * Les expositions que l'enveloppe admet ; `null` quand elle admet tout.
     *
     * Le PEA n'accueille que des actions. `AssetClass` ne dit pas la zone géographique : la
     * restriction aux titres de l'Union n'est pas représentable ici, elle n'est donc pas
     * prétendue.
     *
     * @return ?list<AssetClass>
     */
    public function allowedAssetClasses(): ?array
    {
        return match ($this) {
            self::Pea => [AssetClass::Equity],
            self::Cto => null,
        };
    }

    public function admits(AssetClass $class): bool
    {
        $allowed = $this->allowedAssetClasses();

        return $allowed === null || in_array($class, $allowed, true);
    }
}
