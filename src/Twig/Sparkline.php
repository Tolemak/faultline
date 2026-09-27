<?php

declare(strict_types=1);

namespace App\Twig;

final class Sparkline
{
    /**
     * @param list<int> $values
     */
    public static function points(array $values, int $width, int $height): string
    {
        $count = \count($values);
        if (0 === $count) {
            return '';
        }

        $max = max(1, max($values));
        $step = $count > 1 ? $width / ($count - 1) : 0;
        $baseline = $height - 1;

        $points = [];
        foreach ($values as $index => $value) {
            $x = $count > 1 ? $index * $step : $width / 2;
            $y = $baseline - ($value / $max) * ($height - 2);
            $points[] = \sprintf('%.1f,%.1f', $x, $y);
        }

        return implode(' ', $points);
    }
}
