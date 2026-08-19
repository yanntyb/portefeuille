<?php

namespace App\Contexts\Income\Enums;

/**
 * Origine d'un revenu perçu. Le noyau du contexte n'en connaît aucune en particulier : ajouter
 * un loyer, c'est ajouter un cas ici et une source qui le produit.
 */
enum IncomeSource: string
{
    case Dividend = 'dividend';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Dividend => 'Dividendes',
        };
    }
}
