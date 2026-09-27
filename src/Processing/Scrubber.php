<?php

declare(strict_types=1);

namespace App\Processing;

final class Scrubber
{
    public const string FILTERED = '[filtered]';

    private const string SENSITIVE_KEY = '/password|passwd|secret|token|api[-_]?key|auth|cookie|session|csrf|dsn|private/i';
    private const array DROPPED_HEADERS = ['authorization', 'cookie', 'set-cookie', 'proxy-authorization'];
    private const string CARD_CANDIDATE = '/(?<![\d])(?:\d[ -]?){12,18}\d(?![\d])/';
    private const string IBAN_CANDIDATE = '/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]){11,30}\b/';

    public function scrubEvent(NormalizedEvent $event): NormalizedEvent
    {
        return new NormalizedEvent(
            eventId: $event->eventId,
            occurredAt: $event->occurredAt,
            level: $event->level,
            platform: $event->platform,
            environment: $event->environment,
            release: $event->release,
            transaction: $event->transaction,
            message: null === $event->message ? null : $this->scrubString($event->message),
            messageTemplate: null === $event->messageTemplate ? null : $this->scrubString($event->messageTemplate),
            exceptions: $this->scrubExceptions($event->exceptions),
            tags: $this->scrubTags($event->tags),
            contexts: $this->scrubMap($event->contexts),
            request: null === $event->request ? null : $this->scrubRequest($event->request),
            user: null === $event->user ? null : $this->scrubMap($event->user),
            extra: $this->scrubMap($event->extra),
            fingerprint: $event->fingerprint,
        );
    }

    public function scrub(mixed $value): mixed
    {
        if (\is_string($value)) {
            return $this->scrubString($value);
        }

        if (!\is_array($value)) {
            return $value;
        }

        $scrubbed = [];
        foreach ($value as $key => $item) {
            $scrubbed[$key] = \is_string($key) && $this->isSensitiveKey($key) ? self::FILTERED : $this->scrub($item);
        }

        return $scrubbed;
    }

    public function isSensitiveKey(string $key): bool
    {
        return 1 === preg_match(self::SENSITIVE_KEY, $key);
    }

    public function scrubString(string $value): string
    {
        $value = (string) preg_replace_callback(
            self::CARD_CANDIDATE,
            static fn (array $match): string => Checksum::luhn($match[0]) ? '[card]' : $match[0],
            $value,
        );

        return (string) preg_replace_callback(
            self::IBAN_CANDIDATE,
            static fn (array $match): string => Checksum::iban($match[0]) ? '[iban]' : $match[0],
            $value,
        );
    }

    public function scrubQueryString(string $query): string
    {
        if ('' === $query) {
            return $query;
        }

        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            [$key] = explode('=', $pair, 2);
            $pairs[] = $this->isSensitiveKey(urldecode($key)) ? $key.'='.self::FILTERED : $this->scrubString($pair);
        }

        return implode('&', $pairs);
    }

    public function scrubUrl(string $url): string
    {
        $queryStart = strpos($url, '?');
        if (false === $queryStart) {
            return $this->scrubString($url);
        }

        $fragmentStart = strpos($url, '#', $queryStart);
        $query = false === $fragmentStart ? substr($url, $queryStart + 1) : substr($url, $queryStart + 1, $fragmentStart - $queryStart - 1);
        $fragment = false === $fragmentStart ? '' : substr($url, $fragmentStart);

        return $this->scrubString(substr($url, 0, $queryStart)).'?'.$this->scrubQueryString($query).$fragment;
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    private function scrubMap(array $map): array
    {
        $scrubbed = [];
        foreach ($map as $key => $value) {
            $scrubbed[$key] = $this->isSensitiveKey($key) ? self::FILTERED : $this->scrub($value);
        }

        return $scrubbed;
    }

    /**
     * @param array<string, string> $tags
     *
     * @return array<string, string>
     */
    private function scrubTags(array $tags): array
    {
        $scrubbed = [];
        foreach ($tags as $key => $value) {
            $scrubbed[$key] = $this->isSensitiveKey($key) ? self::FILTERED : $this->scrubString($value);
        }

        return $scrubbed;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function scrubRequest(array $request): array
    {
        $headers = \is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $keptHeaders = [];
        foreach ($headers as $name => $value) {
            if (!\in_array(strtolower((string) $name), self::DROPPED_HEADERS, true)) {
                $keptHeaders[(string) $name] = $value;
            }
        }
        unset($request['headers'], $request['cookies']);

        $query = $request['query_string'] ?? null;
        unset($request['query_string']);
        $url = $request['url'] ?? null;
        unset($request['url']);

        $scrubbed = $this->scrubMap($request);
        if ([] !== $keptHeaders) {
            $scrubbed['headers'] = $this->scrubMap($keptHeaders);
        }
        if (\is_string($url)) {
            $scrubbed['url'] = $this->scrubUrl($url);
        }
        if (\is_string($query)) {
            $scrubbed['query_string'] = $this->scrubQueryString($query);
        } elseif (\is_array($query)) {
            $scrubbed['query_string'] = $this->scrub($query);
        }

        return $scrubbed;
    }

    /**
     * @param list<array{type: ?string, value: ?string, module: ?string, mechanism: ?array<string, mixed>, frames: list<array<string, mixed>>}> $exceptions
     *
     * @return list<array{type: ?string, value: ?string, module: ?string, mechanism: ?array<string, mixed>, frames: list<array<string, mixed>>}>
     */
    private function scrubExceptions(array $exceptions): array
    {
        $scrubbed = [];
        foreach ($exceptions as $exception) {
            $frames = [];
            foreach ($exception['frames'] as $frame) {
                if (\is_array($frame['vars'] ?? null)) {
                    $frame['vars'] = $this->scrub($frame['vars']);
                }
                $frames[] = $frame;
            }

            $exception['value'] = null === $exception['value'] ? null : $this->scrubString($exception['value']);
            $exception['frames'] = $frames;
            $scrubbed[] = $exception;
        }

        return $scrubbed;
    }
}
