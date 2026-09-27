<?php

declare(strict_types=1);

namespace App\Ingest\Envelope;

use App\Ingest\IngestException;
use App\Ingest\JsonDecoder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class EnvelopeParser
{
    public function __construct(
        private JsonDecoder $json,
        #[Autowire(param: 'faultline.ingest.envelope_max_items')]
        private int $maxItems,
    ) {
    }

    public function parse(string $body): Envelope
    {
        $position = 0;
        $length = \strlen($body);

        $headerLine = $this->readLine($body, $position);
        if ('' === trim($headerLine)) {
            throw IngestException::invalidPayload('Envelope header is missing.');
        }
        $headers = $this->json->decodeObject($headerLine);

        $items = [];
        while ($position < $length) {
            $itemHeaderLine = $this->readLine($body, $position);
            if ('' === trim($itemHeaderLine)) {
                continue;
            }

            $itemHeaders = $this->json->decodeObject($itemHeaderLine);
            $type = $itemHeaders['type'] ?? null;
            if (!\is_string($type) || '' === $type) {
                throw IngestException::invalidPayload('Envelope item type is missing.');
            }

            $items[] = new EnvelopeItem($type, $itemHeaders, $this->readPayload($body, $position, $itemHeaders['length'] ?? null));

            if (\count($items) > $this->maxItems) {
                throw IngestException::invalidPayload('Too many envelope items.');
            }
        }

        return new Envelope($headers, $items);
    }

    private function readPayload(string $body, int &$position, mixed $declaredLength): string
    {
        if (null === $declaredLength) {
            return $this->readLine($body, $position);
        }

        if (!\is_int($declaredLength) || $declaredLength < 0) {
            throw IngestException::invalidPayload('Envelope item length is invalid.');
        }

        $payload = substr($body, $position, $declaredLength);
        if (\strlen($payload) !== $declaredLength) {
            throw IngestException::invalidPayload('Envelope item is truncated.');
        }

        $position += $declaredLength;
        if ($position < \strlen($body) && "\n" === $body[$position]) {
            ++$position;
        }

        return $payload;
    }

    private function readLine(string $body, int &$position): string
    {
        $newline = strpos($body, "\n", $position);
        if (false === $newline) {
            $line = substr($body, $position);
            $position = \strlen($body);

            return $line;
        }

        $line = substr($body, $position, $newline - $position);
        $position = $newline + 1;

        return $line;
    }
}
