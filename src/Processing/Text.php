<?php

declare(strict_types=1);

namespace App\Processing;

final class Text
{
    public static function of(mixed $value, int $maxLength): ?string
    {
        $text = match (true) {
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            \is_bool($value) => $value ? 'true' : 'false',
            default => null,
        };

        if (null === $text) {
            return null;
        }

        $text = trim(mb_scrub($text, 'UTF-8'));

        return '' === $text ? null : mb_substr($text, 0, $maxLength);
    }
}
