<?php

declare(strict_types=1);

namespace App\Processing;

use App\Enum\Level;

final class EventNormalizer
{
    private const int MAX_EXCEPTIONS = 10;
    private const int MAX_FRAMES = 250;
    private const int MAX_CONTEXT_LINES = 10;
    private const int MAX_TAGS = 100;
    private const int MAX_FINGERPRINT_PARTS = 20;
    private const int EARLIEST_TIMESTAMP = 946684800;
    private const int MAX_CLOCK_SKEW = 3600;

    /**
     * @param array<string, mixed> $payload
     */
    public function normalize(string $eventId, array $payload, \DateTimeImmutable $receivedAt): NormalizedEvent
    {
        [$message, $template] = $this->message($payload);

        return new NormalizedEvent(
            eventId: $eventId,
            occurredAt: $this->timestamp($payload['timestamp'] ?? null, $receivedAt),
            level: Level::fromSentry($payload['level'] ?? null),
            platform: Text::of($payload['platform'] ?? null, 64) ?? 'other',
            environment: Text::of($payload['environment'] ?? null, 64),
            release: Text::of($payload['release'] ?? null, 200),
            transaction: Text::of($payload['transaction'] ?? null, 200),
            message: $message,
            messageTemplate: $template,
            exceptions: $this->exceptions($payload['exception'] ?? null),
            tags: $this->tags($payload['tags'] ?? null),
            contexts: $this->map($payload['contexts'] ?? null),
            request: $this->request($payload['request'] ?? null),
            user: $this->user($payload['user'] ?? null),
            extra: $this->map($payload['extra'] ?? null),
            fingerprint: $this->fingerprint($payload['fingerprint'] ?? null),
        );
    }

    private function timestamp(mixed $value, \DateTimeImmutable $receivedAt): \DateTimeImmutable
    {
        $timestamp = match (true) {
            \is_int($value), \is_float($value) => (float) $value,
            \is_string($value) && is_numeric($value) => (float) $value,
            \is_string($value) => false !== ($parsed = strtotime($value)) ? (float) $parsed : null,
            default => null,
        };

        if (null === $timestamp || $timestamp < self::EARLIEST_TIMESTAMP || $timestamp > $receivedAt->getTimestamp() + self::MAX_CLOCK_SKEW) {
            return $receivedAt;
        }

        $date = \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $timestamp));

        return false === $date ? $receivedAt : $date->setTimezone($receivedAt->getTimezone());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{?string, ?string}
     */
    private function message(array $payload): array
    {
        $logentry = $payload['logentry'] ?? null;
        $message = $payload['message'] ?? null;

        $formatted = null;
        $template = null;

        if (\is_array($logentry)) {
            $formatted = Text::of($logentry['formatted'] ?? null, 8192);
            $template = Text::of($logentry['message'] ?? null, 8192);
        }

        if (\is_array($message)) {
            $formatted ??= Text::of($message['formatted'] ?? null, 8192);
            $template ??= Text::of($message['message'] ?? null, 8192);
        } elseif (\is_string($message)) {
            $formatted ??= Text::of($message, 8192);
        }

        return [$formatted ?? $template, $template ?? $formatted];
    }

    /**
     * @return list<array{type: ?string, value: ?string, module: ?string, mechanism: ?array<string, mixed>, frames: list<array<string, mixed>>}>
     */
    private function exceptions(mixed $value): array
    {
        if (\is_array($value) && \is_array($value['values'] ?? null)) {
            $value = $value['values'];
        }
        if (!\is_array($value) || !array_is_list($value)) {
            return [];
        }

        $exceptions = [];
        foreach (\array_slice($value, -self::MAX_EXCEPTIONS) as $exception) {
            if (!\is_array($exception)) {
                continue;
            }

            $stacktrace = $exception['stacktrace'] ?? null;
            $exceptions[] = [
                'type' => Text::of($exception['type'] ?? null, 256),
                'value' => Text::of($exception['value'] ?? null, 4096),
                'module' => Text::of($exception['module'] ?? null, 256),
                'mechanism' => $this->nullableMap($exception['mechanism'] ?? null),
                'frames' => $this->frames(\is_array($stacktrace) ? ($stacktrace['frames'] ?? null) : null),
            ];
        }

        return $exceptions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function frames(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return [];
        }

        $frames = [];
        foreach (\array_slice($value, -self::MAX_FRAMES) as $frame) {
            if (!\is_array($frame)) {
                continue;
            }

            $frames[] = [
                'filename' => Text::of($frame['filename'] ?? null, 1024),
                'abs_path' => Text::of($frame['abs_path'] ?? null, 1024),
                'module' => Text::of($frame['module'] ?? null, 256),
                'function' => Text::of($frame['function'] ?? null, 256),
                'lineno' => \is_int($frame['lineno'] ?? null) ? $frame['lineno'] : null,
                'colno' => \is_int($frame['colno'] ?? null) ? $frame['colno'] : null,
                'in_app' => \is_bool($frame['in_app'] ?? null) ? $frame['in_app'] : null,
                'context_line' => Text::of($frame['context_line'] ?? null, 1024),
                'pre_context' => $this->lines($frame['pre_context'] ?? null),
                'post_context' => $this->lines($frame['post_context'] ?? null),
                'vars' => $this->nullableMap($frame['vars'] ?? null),
            ];
        }

        return $frames;
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $lines = [];
        foreach (\array_slice(array_values($value), -self::MAX_CONTEXT_LINES) as $line) {
            $lines[] = \is_string($line) ? mb_substr($line, 0, 1024) : '';
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function tags(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $pairs = [];
        foreach ($value as $key => $tag) {
            if (\is_array($tag) && array_is_list($tag) && 2 === \count($tag)) {
                [$key, $tag] = $tag;
            }
            $name = Text::of($key, 200);
            $text = Text::of($tag, 200);
            if (null !== $name && null !== $text) {
                $pairs[$name] = $text;
            }
            if (\count($pairs) >= self::MAX_TAGS) {
                break;
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function request(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $request = [
            'url' => Text::of($value['url'] ?? null, 2048),
            'method' => Text::of($value['method'] ?? null, 16),
            'query_string' => \is_array($value['query_string'] ?? null) ? $value['query_string'] : Text::of($value['query_string'] ?? null, 4096),
            'headers' => $this->tags($value['headers'] ?? null),
            'data' => $value['data'] ?? null,
            'env' => $this->nullableMap($value['env'] ?? null),
        ];

        return array_filter($request, static fn (mixed $item): bool => null !== $item && [] !== $item);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function user(mixed $value): ?array
    {
        $user = $this->nullableMap($value);
        if (null === $user) {
            return null;
        }

        unset($user['ip_address']);

        return [] === $user ? null : $user;
    }

    /**
     * @return list<string>|null
     */
    private function fingerprint(mixed $value): ?array
    {
        if (!\is_array($value) || [] === $value) {
            return null;
        }

        $parts = [];
        foreach (\array_slice(array_values($value), 0, self::MAX_FINGERPRINT_PARTS) as $part) {
            $text = Text::of($part, 512);
            if (null !== $text) {
                $parts[] = $text;
            }
        }

        return [] === $parts ? null : $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function map(mixed $value): array
    {
        return $this->nullableMap($value) ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nullableMap(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }
}
