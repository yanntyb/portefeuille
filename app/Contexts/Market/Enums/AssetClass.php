<?php

namespace App\Contexts\Market\Enums;

/**
 * L'exposition d'un actif : à quoi son porteur est exposé, indépendamment de la façon dont il le
 * détient. `InstrumentType` dit l'enveloppe — titre vif, ETF, contrat à terme —, cet enum dit la
 * classe d'actif. Un ETF MSCI World est une enveloppe ETF sur une exposition actions ; l'or Xetra
 * et un contrat à terme sur l'argent sont deux enveloppes sur une même exposition.
 *
 * L'ordre des cas est un contrat : il fixe l'ordre des lignes du résumé patrimonial et
 * l'empilement des bandes de son graphe. Le réordonner change ce que le lecteur voit.
 */
enum AssetClass: string
{
    case Equity = 'equity';
    case Bond = 'bond';
    case Commodity = 'commodity';
    case Crypto = 'crypto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * L'exposition posée à la création d'un instrument qui n'en déclare aucune. C'est un défaut,
     * jamais une dérivation : la valeur est écrite en base une fois, puis corrigible ligne à ligne.
     *
     * Un ETF est présumé actions — vrai des sept que porte le portefeuille, faux d'un futur ETF
     * obligataire ou aurifère, qu'il faudra corriger à la main. Modifier cette correspondance
     * casse un test, donc reste délibéré.
     */
    public static function defaultForType(InstrumentType $type): self
    {
        return match ($type) {
            InstrumentType::Stock, InstrumentType::ETF => self::Equity,
            InstrumentType::Bond => self::Bond,
            InstrumentType::Commodity => self::Commodity,
            InstrumentType::Crypto => self::Crypto,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Equity => 'Actions',
            self::Bond => 'Obligations',
            self::Commodity => 'Matières premières',
            self::Crypto => 'Crypto',
        };
    }

    /** Segment d'URL de la page liste. En français : une adresse est du texte vu par le lecteur. */
    public function slug(): string
    {
        return match ($this) {
            self::Equity => 'actions',
            self::Bond => 'obligations',
            self::Commodity => 'matieres-premieres',
            self::Crypto => 'crypto',
        };
    }

    /**
     * Jeton de teinte, pas une couleur : ECharts peint son SVG lui-même et ne résout pas les
     * variables CSS, donc `palette()` côté front tient un hexadécimal par jeton et par thème.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Equity => 'value',
            self::Bond => 'bond',
            self::Commodity => 'commodity',
            self::Crypto => 'crypto',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Equity => 'heroicon-o-chart-bar',
            self::Bond => 'heroicon-o-document-text',
            self::Commodity => 'heroicon-o-cube',
            self::Crypto => 'heroicon-o-currency-bitcoin',
        };
    }

    /**
     * Une exposition dont les actifs portent une répartition sectorielle. C'est l'enveloppe qui
     * décide : `YahooFinanceAdapter::supportsSectors()` ne décompose que les `Stock` et les `ETF`,
     * qui sont l'un et l'autre des expositions actions.
     */
    public function hasSectors(): bool
    {
        return $this === self::Equity;
    }
}
