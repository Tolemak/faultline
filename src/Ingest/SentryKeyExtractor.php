<?php

declare(strict_types=1);

namespace App\Ingest;

use Symfony\Component\HttpFoundation\Request;

final class SentryKeyExtractor
{
    private const string KEY_PATTERN = '/^[a-f0-9]{32}$/';

    public function fromRequest(Request $request): ?string
    {
        $header = $request->headers->get('X-Sentry-Auth');
        if (null !== $header && 1 === preg_match('/(?:^|[\s,])sentry_key\s*=\s*([^,\s]+)/i', $header, $match)) {
            return $this->valid($match[1]);
        }

        $query = $request->query->all()['sentry_key'] ?? null;

        return \is_string($query) ? $this->valid($query) : null;
    }

    public function fromDsn(mixed $dsn): ?string
    {
        if (!\is_string($dsn)) {
            return null;
        }

        $user = parse_url($dsn, \PHP_URL_USER);

        return \is_string($user) ? $this->valid($user) : null;
    }

    private function valid(string $key): ?string
    {
        $key = strtolower(trim($key));

        return 1 === preg_match(self::KEY_PATTERN, $key) ? $key : null;
    }
}
