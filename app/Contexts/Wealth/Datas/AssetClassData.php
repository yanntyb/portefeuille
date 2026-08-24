<?php

namespace App\Contexts\Wealth\Datas;

use App\Contexts\Wealth\Ports\AssetClassPort;
use JsonSerializable;

/** Une ligne de classe d'actif du résumé : ce qu'elle est, ce qu'elle vaut, et l'écart à sa mise. */
readonly class AssetClassData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
        public string $color,
        public float $value,
        public float $invested,
        public float $gain,
        public ?float $gainPct,
    ) {}

    public static function from(AssetClassPort $class, ClassSnapshotData $snapshot): self
    {
        return new self(
            key: $class->key(),
            label: $class->label(),
            href: $class->href(),
            color: $class->color(),
            value: round($snapshot->value, 2),
            invested: round($snapshot->invested, 2),
            gain: self::gainOf($snapshot),
            gainPct: self::gainPctOf($snapshot),
        );
    }

    public static function gainOf(ClassSnapshotData $snapshot): float
    {
        return round($snapshot->value - $snapshot->invested, 2);
    }

    /**
     * Nul, et non zéro, quand la mise est nulle ou négative : un bien financé à plus de 100 % n'a
     * pas de mise à laquelle rapporter son gain, et « 0 % » mentirait.
     */
    public static function gainPctOf(ClassSnapshotData $snapshot): ?float
    {
        return $snapshot->invested <= 0.0
            ? null
            : round(self::gainOf($snapshot) / $snapshot->invested * 100, 2);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'href' => $this->href,
            'color' => $this->color,
            'value' => $this->value,
            'invested' => $this->invested,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
