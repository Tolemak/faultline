<?php

declare(strict_types=1);

namespace App\Ingest;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BodyDecoder
{
    private const int CHUNK_SIZE = 1024;

    public function __construct(
        #[Autowire(param: 'faultline.ingest.max_wire_bytes')]
        private int $maxWireBytes,
        #[Autowire(param: 'faultline.ingest.max_inflated_bytes')]
        private int $maxInflatedBytes,
    ) {
    }

    public function assertDeclaredLength(?string $contentLength): void
    {
        if (null !== $contentLength && ctype_digit($contentLength) && (int) $contentLength > $this->maxWireBytes) {
            throw IngestException::payloadTooLarge();
        }
    }

    public function decode(string $body, ?string $contentEncoding): string
    {
        if (\strlen($body) > $this->maxWireBytes) {
            throw IngestException::payloadTooLarge();
        }

        $encoding = strtolower(trim((string) $contentEncoding));

        return match ($encoding) {
            '', 'identity', 'none' => $this->plain($body),
            'gzip', 'x-gzip' => $this->inflate($body, \ZLIB_ENCODING_GZIP),
            'deflate' => $this->inflateDeflate($body),
            default => throw IngestException::unsupportedEncoding($encoding),
        };
    }

    private function plain(string $body): string
    {
        if (\strlen($body) > $this->maxInflatedBytes) {
            throw IngestException::payloadTooLarge();
        }

        return $body;
    }

    private function inflateDeflate(string $body): string
    {
        try {
            return $this->inflate($body, \ZLIB_ENCODING_DEFLATE);
        } catch (IngestException $e) {
            if (!$e->isInvalidPayload()) {
                throw $e;
            }

            return $this->inflate($body, \ZLIB_ENCODING_RAW);
        }
    }

    private function inflate(string $body, int $encoding): string
    {
        $context = inflate_init($encoding);
        if (false === $context) {
            throw IngestException::invalidPayload('Cannot initialise decompression.');
        }

        $output = '';
        foreach (str_split($body, self::CHUNK_SIZE) as $chunk) {
            $output .= $this->inflateChunk($context, $chunk, \ZLIB_SYNC_FLUSH);
            $this->assertInflatedSize($output);
            if (\ZLIB_STREAM_END === inflate_get_status($context)) {
                return $output;
            }
        }

        $output .= $this->inflateChunk($context, '', \ZLIB_FINISH);
        $this->assertInflatedSize($output);

        if (\ZLIB_STREAM_END !== inflate_get_status($context)) {
            throw IngestException::invalidPayload('Compressed body is truncated.');
        }

        return $output;
    }

    private function inflateChunk(\InflateContext $context, string $chunk, int $flush): string
    {
        $part = @inflate_add($context, $chunk, $flush);
        if (false === $part) {
            throw IngestException::invalidPayload('Compressed body is corrupt.');
        }

        return $part;
    }

    private function assertInflatedSize(string $output): void
    {
        if (\strlen($output) > $this->maxInflatedBytes) {
            throw IngestException::payloadTooLarge();
        }
    }
}
