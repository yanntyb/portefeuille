<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\AssetClass;

/**
 * Le périmètre d'une lecture du portefeuille : quelles expositions, quelle enveloppe. Un seul
 * objet là où six actions portaient jusqu'ici un `?list<AssetClass> $classes`, chacune avec son
 * propre nom de cache — `BuildExposureSeries` y avait ajouté un `?int $walletId` que les autres
 * n'avaient pas, et le filtre par enveloppe ne pouvait donc pas se propager.
 *
 * Il vit dans `Market` parce que c'est déjà le vocabulaire partagé : `AssetClass` y est lue depuis
 * tous les contextes, et un périmètre n'est qu'un choix de classes et d'enveloppe.
 *
 * `null` veut dire « aucun filtre », jamais « rien » : `all()` admet tout.
 */
readonly class HoldingScope
{
    /** @param  ?list<AssetClass>  $classes */
    private function __construct(
        public ?array $classes,
        public ?int $walletId,
    ) {}

    public static function all(): self
    {
        return new self(null, null);
    }

    /** @param  list<AssetClass>  $classes */
    public static function ofClasses(array $classes): self
    {
        return new self($classes, null);
    }

    public static function ofWallet(int $walletId): self
    {
        return new self(null, $walletId);
    }

    /** @param  ?list<AssetClass>  $classes */
    public static function of(?array $classes, ?int $walletId): self
    {
        return new self($classes, $walletId);
    }

    public function admits(AssetClass $class): bool
    {
        return $this->classes === null || in_array($class, $this->classes, strict: true);
    }

    public function admitsWallet(int $walletId): bool
    {
        return $this->walletId === null || $this->walletId === $walletId;
    }

    /**
     * Le suffixe qu'un cache ajoute à son nom pour ce périmètre — vide quand il n'y a pas de
     * filtre. Seule définition de cette traduction : une série ou une performance agrège ses
     * transactions avant d'exister, elle ne se découpe pas après coup, et deux périmètres sous un
     * même nom se serviraient le résultat l'un de l'autre.
     */
    public function cacheKey(): string
    {
        $key = $this->classes === null ? '' : '.'.implode('-', array_unique(array_map(
            fn (AssetClass $class): string => $class->value,
            $this->classes,
        )));

        return $this->walletId === null ? $key : $key.'.enveloppe-'.$this->walletId;
    }
}
