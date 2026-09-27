<?php

declare(strict_types=1);

namespace App\Ingest;

final class IngestException extends \RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(string $message, public readonly int $statusCode, public readonly array $headers = [])
    {
        parent::__construct($message);
    }

    public static function invalidPayload(string $reason): self
    {
        return new self($reason, 400);
    }

    public static function unauthorized(): self
    {
        return new self('Invalid or missing sentry_key.', 401);
    }

    public static function forbiddenOrigin(): self
    {
        return new self('Origin not allowed.', 403);
    }

    public static function payloadTooLarge(): self
    {
        return new self('Payload too large.', 413);
    }

    public static function unsupportedEncoding(string $encoding): self
    {
        return new self(\sprintf('Unsupported content encoding "%s".', $encoding), 415);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self('Rate limit exceeded.', 429, [
            'Retry-After' => (string) $retryAfter,
            'X-Sentry-Rate-Limits' => $retryAfter.'::organization',
        ]);
    }

    public function isInvalidPayload(): bool
    {
        return 400 === $this->statusCode;
    }
}
