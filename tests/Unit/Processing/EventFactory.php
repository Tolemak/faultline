<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Processing\EventNormalizer;
use App\Processing\NormalizedEvent;

final class EventFactory
{
    /**
     * @param array<mixed> $payload
     */
    public static function normalized(array $payload): NormalizedEvent
    {
        return (new EventNormalizer())->normalize('9ec79c33ec9942ab8353589fcb2e04dc', $payload, new \DateTimeImmutable('2026-09-27 12:00:00'));
    }

    /**
     * @param list<array<string, mixed>> $frames
     *
     * @return array<string, mixed>
     */
    public static function exception(string $type, string $value, array $frames): array
    {
        return ['exception' => ['values' => [['type' => $type, 'value' => $value, 'stacktrace' => ['frames' => $frames]]]]];
    }
}
