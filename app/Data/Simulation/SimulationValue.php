<?php

namespace App\Data\Simulation;

readonly class SimulationValue
{
    public function __construct(
        public ?float $numeric,
        public ValueFormat $format,
    ) {}

    public function formatted(): string
    {
        if ($this->numeric === null) {
            return '—';
        }

        return match ($this->format) {
            ValueFormat::Euro => number_format($this->numeric, 2, ',', ' ').' €',
            ValueFormat::Percent => number_format($this->numeric * 100, 2, ',', ' ').' %',
            ValueFormat::Plain => floor($this->numeric) == $this->numeric
                ? (string) (int) $this->numeric
                : number_format($this->numeric, 2, ',', ' '),
        };
    }

    public static function parse(string $raw): self
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '—') {
            $format = ValueFormat::Plain;
            if (str_contains($raw, '€')) {
                $format = ValueFormat::Euro;
            } elseif (str_contains($raw, '%')) {
                $format = ValueFormat::Percent;
            }

            return new self(null, $format);
        }

        $isPercent = str_contains($raw, '%');
        $isEuro = str_contains($raw, '€');

        $cleaned = preg_replace('/[^\d,.\-]/', '', $raw);
        $cleaned = str_replace(' ', '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);

        if (! is_numeric($cleaned)) {
            $format = $isEuro ? ValueFormat::Euro : ($isPercent ? ValueFormat::Percent : ValueFormat::Plain);

            return new self(null, $format);
        }

        $value = (float) $cleaned;

        if ($isPercent) {
            return new self($value / 100, ValueFormat::Percent);
        }

        if ($isEuro) {
            return new self($value, ValueFormat::Euro);
        }

        return new self($value, ValueFormat::Plain);
    }

    public static function euro(float $value): self
    {
        return new self($value, ValueFormat::Euro);
    }

    public static function percent(float $value): self
    {
        return new self($value, ValueFormat::Percent);
    }

    public static function plain(float $value): self
    {
        return new self($value, ValueFormat::Plain);
    }
}
