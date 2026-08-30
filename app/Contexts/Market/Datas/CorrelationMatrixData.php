<?php

namespace App\Contexts\Market\Datas;

use JsonSerializable;

/**
 * Une matrice de corrélations, carrée et symétrique. `keys` donne l'ordre des lignes comme celui
 * des colonnes : la case `rows[i][j]` corrèle `keys[i]` à `keys[j]`.
 *
 * Une case vaut `null` quand la paire n'a pas assez de séances communes ou qu'une des deux séries
 * ne varie pas : mieux vaut l'avouer qu'avancer un chiffre que le hasard suffit à expliquer.
 */
readonly class CorrelationMatrixData implements JsonSerializable
{
    /**
     * @param  list<int|string>  $keys
     * @param  list<list<?float>>  $rows
     */
    public function __construct(
        public array $keys,
        public array $rows,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'keys' => $this->keys,
            'rows' => $this->rows,
        ];
    }
}
