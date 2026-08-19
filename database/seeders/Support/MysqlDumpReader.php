<?php

namespace Database\Seeders\Support;

use Generator;

/**
 * Lecteur minimal de dump `mysqldump`, juste assez pour rejouer les données d'un snapshot.
 *
 * Les `INSERT` produits par mysqldump n'ont pas de liste de colonnes et regroupent des milliers
 * de tuples sur une seule ligne : l'appelant reçoit des tableaux positionnels et fait
 * lui-même la correspondance avec les colonnes du `CREATE TABLE`.
 */
final class MysqlDumpReader
{
    public function __construct(private readonly string $path) {}

    /**
     * Parcourt les tuples d'une table, sans jamais charger le fichier entier en mémoire.
     *
     * Chaque tuple est rendu dès qu'il est analysé : une ligne d'`INSERT` du dump pèse jusqu'à
     * un mégaoctet et les prix se comptent en dizaines de milliers de lignes.
     *
     * @return Generator<int, list<string|null>>
     */
    public function rows(string $table): Generator
    {
        if (! is_file($this->path)) {
            return;
        }

        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return;
        }

        $prefix = 'INSERT INTO `'.$table.'` VALUES ';
        $index = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                if (! str_starts_with($line, $prefix)) {
                    continue;
                }

                // Clé explicite : un `yield from` repartirait de zéro à chaque instruction et
                // `iterator_to_array()` écraserait les tuples des instructions précédentes.
                foreach ($this->parseTuples(substr($line, strlen($prefix))) as $row) {
                    yield $index++ => $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Découpe la liste `(…),(…),(…);` d'une instruction en tuples.
     *
     * @return Generator<int, list<string|null>>
     */
    private function parseTuples(string $values): Generator
    {
        $offset = 0;

        while (($start = strpos($values, '(', $offset)) !== false) {
            [$row, $offset] = $this->parseTuple($values, $start + 1);

            yield $row;
        }
    }

    /**
     * Lit un tuple à partir du caractère suivant sa parenthèse ouvrante.
     *
     * @return array{0: list<string|null>, 1: int} le tuple et la position juste après sa
     *                                             parenthèse fermante
     */
    private function parseTuple(string $values, int $offset): array
    {
        $length = strlen($values);
        $fields = [];

        while ($offset < $length) {
            if ($values[$offset] === "'") {
                [$field, $offset] = $this->parseString($values, $offset + 1);
            } else {
                $run = strcspn($values, ',)', $offset);
                $token = trim(substr($values, $offset, $run));
                $offset += $run;
                $field = strcasecmp($token, 'NULL') === 0 ? null : $token;
            }

            $fields[] = $field;

            if ($offset < $length && $values[$offset] === ',') {
                $offset++;

                continue;
            }

            if ($offset < $length && $values[$offset] === ')') {
                $offset++;
            }

            break;
        }

        return [$fields, $offset];
    }

    /**
     * Lit une chaîne à partir du caractère suivant son apostrophe ouvrante.
     *
     * Une apostrophe se code `\'` chez mysqldump et `''` en SQL standard : les deux formes sont
     * acceptées, faute de quoi une note libre couperait le tuple en deux.
     *
     * @return array{0: string, 1: int} la chaîne décodée et la position juste après son
     *                                  apostrophe fermante
     */
    private function parseString(string $values, int $offset): array
    {
        $length = strlen($values);
        $decoded = '';

        while ($offset < $length) {
            $run = strcspn($values, "\\'", $offset);
            $decoded .= substr($values, $offset, $run);
            $offset += $run;

            if ($offset >= $length) {
                break;
            }

            if ($values[$offset] === '\\') {
                $decoded .= $this->unescape($values[$offset + 1] ?? '');
                $offset += 2;

                continue;
            }

            if (($values[$offset + 1] ?? '') === "'") {
                $decoded .= "'";
                $offset += 2;

                continue;
            }

            return [$decoded, $offset + 1];
        }

        return [$decoded, $offset];
    }

    /**
     * Traduit le caractère suivant un antislash.
     *
     * Tout ce qui n'est pas une séquence connue vaut le caractère lui-même, ce qui couvre `\'`,
     * `\"` et `\\`.
     */
    private function unescape(string $character): string
    {
        return match ($character) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => chr(8),
            'Z' => chr(26),
            '0' => "\0",
            default => $character,
        };
    }
}
