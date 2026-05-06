<?php

namespace App\Infrastructure\Support;

final class ChartColors
{
    public const PALETTE = [
        'rgb(59, 130, 246)',
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(239, 68, 68)',
        'rgb(139, 92, 246)',
        'rgb(236, 72, 153)',
        'rgb(20, 184, 166)',
        'rgb(249, 115, 22)',
        'rgb(99, 102, 241)',
        'rgb(34, 197, 94)',
    ];

    public static function at(int $index): string
    {
        return self::PALETTE[$index % count(self::PALETTE)];
    }

    /**
     * @return array{border: string, bg: string}
     */
    public static function withAlpha(int $index, float $alpha = 0.4): array
    {
        $rgb = self::at($index);
        $rgba = str_replace('rgb(', 'rgba(', str_replace(')', ", {$alpha})", $rgb));

        return ['border' => $rgb, 'bg' => $rgba];
    }
}
