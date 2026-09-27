<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessEvent
{
    public function __construct(
        public int $projectId,
        public string $eventId,
        public string $payload,
        public \DateTimeImmutable $receivedAt,
    ) {
    }
}
