<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Une ligne de classe d'actif du résumé : sa valeur, sa mise, et l'écart entre les deux. */
readonly class AssetClassData implements JsonSerializable
{
    public function __construct(
        public float $value,
        public float $invested,
        public float $gain,
        public ?float $gainPct,
    ) {}

    /**
     * Le pourcentage est nul, et non zéro, quand la mise est nulle ou négative : un bien financé
     * à plus de 100 % n'a pas de mise à laquelle rapporter son gain, et « 0 % » mentirait.
     */
    public static function from(ClassSnapshotData $snapshot): self
    {
        $gain = round($snapshot->value - $snapshot->invested, 2);

        return new self(
            value: round($snapshot->value, 2),
            invested: round($snapshot->invested, 2),
            gain: $gain,
            gainPct: $snapshot->invested <= 0.0 ? null : round($gain / $snapshot->invested * 100, 2),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'invested' => $this->invested,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
