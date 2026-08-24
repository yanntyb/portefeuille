<?php

namespace App\Contexts\Income\Enums;

use App\Contexts\Market\Enums\AssetClass;

/**
 * Origine d'un revenu perçu. Le noyau du contexte n'en connaît aucune en particulier : ajouter
 * un loyer, c'est ajouter un cas ici et une source qui le produit.
 */
enum IncomeSource: string
{
    case Dividend = 'dividend';
    case Rent = 'rent';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * L'origine de revenu d'une exposition, ou `null` quand elle n'en produit aucune.
     *
     * Au plus une exposition par origine : le revenu du patrimoine se filtre par origine, jamais
     * par exposition, donc deux expositions renvoyant `Dividend` compteraient deux fois les mêmes
     * dividendes. Les obligations n'ont pas de ligne pour cette raison, et parce que rien ne les
     * alimente — `YahooFinanceAdapter::covers()` ne les couvre pas.
     */
    public static function forAssetClass(AssetClass $class): ?self
    {
        return match ($class) {
            AssetClass::Equity => self::Dividend,
            AssetClass::Bond, AssetClass::Commodity, AssetClass::Crypto => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Dividend => 'Dividendes',
            self::Rent => 'Loyers',
        };
    }
}
