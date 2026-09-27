<?php

declare(strict_types=1);

namespace App\Ingest\Envelope;

final readonly class EnvelopeItem
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public string $type,
        public array $headers,
        public string $payload,
    ) {
    }
}
