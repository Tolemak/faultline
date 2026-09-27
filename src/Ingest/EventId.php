<?php

declare(strict_types=1);

namespace App\Ingest;

final class EventId
{
    public static function normalize(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $id = strtolower(str_replace('-', '', trim($value)));

        return 1 === preg_match('/^[0-9a-f]{32}$/', $id) ? $id : null;
    }

    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
