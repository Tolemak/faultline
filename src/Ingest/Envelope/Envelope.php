<?php

declare(strict_types=1);

namespace App\Ingest\Envelope;

final readonly class Envelope
{
    /**
     * @param array<mixed>       $headers
     * @param list<EnvelopeItem> $items
     */
    public function __construct(
        public array $headers,
        public array $items,
    ) {
    }

    /**
     * @return list<EnvelopeItem>
     */
    public function eventItems(): array
    {
        return array_values(array_filter($this->items, static fn (EnvelopeItem $item): bool => 'event' === $item->type));
    }

    public function header(string $name): mixed
    {
        return $this->headers[$name] ?? null;
    }
}
