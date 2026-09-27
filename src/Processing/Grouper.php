<?php

declare(strict_types=1);

namespace App\Processing;

final class Grouper
{
    private const array DEFAULT_MARKERS = ['{{ default }}', '{{default}}'];

    public function fingerprint(NormalizedEvent $event): string
    {
        return hash('sha256', implode("\n", $this->parts($event)));
    }

    /**
     * @return list<string>
     */
    public function parts(NormalizedEvent $event): array
    {
        if (null === $event->fingerprint) {
            return $this->defaultParts($event);
        }

        $parts = [];
        foreach ($event->fingerprint as $part) {
            if (\in_array($part, self::DEFAULT_MARKERS, true)) {
                array_push($parts, ...$this->defaultParts($event));
            } else {
                $parts[] = 'custom:'.$part;
            }
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function defaultParts(NormalizedEvent $event): array
    {
        $parts = $this->exceptionParts($event);
        if ([] !== $parts) {
            return $parts;
        }

        if (null !== $event->messageTemplate) {
            return ['message:'.self::normalizeMessage($event->messageTemplate)];
        }

        return ['empty:'.$event->platform];
    }

    /**
     * @return list<string>
     */
    private function exceptionParts(NormalizedEvent $event): array
    {
        $parts = [];
        foreach ($event->exceptions as $exception) {
            $frames = $this->groupingFrames($exception['frames']);
            if ([] === $frames && null === $exception['type']) {
                continue;
            }

            $parts[] = 'type:'.($exception['type'] ?? '');
            foreach ($frames as $frame) {
                $parts[] = 'frame:'.$this->frameKey($frame);
            }
            if ([] === $frames && null !== $exception['value']) {
                $parts[] = 'value:'.self::normalizeMessage($exception['value']);
            }
        }

        return $parts;
    }

    /**
     * @param list<array<string, mixed>> $frames
     *
     * @return list<array<string, mixed>>
     */
    private function groupingFrames(array $frames): array
    {
        $inApp = array_values(array_filter($frames, static fn (array $frame): bool => true === ($frame['in_app'] ?? null)));

        return [] === $inApp ? $frames : $inApp;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function frameKey(array $frame): string
    {
        $location = $frame['module'] ?? $frame['filename'] ?? $frame['abs_path'] ?? '';
        $function = $frame['function'] ?? '';

        return (\is_string($location) ? $location : '').'|'.(\is_string($function) ? $function : '');
    }

    public static function normalizeMessage(string $message): string
    {
        $patterns = [
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '<uuid>',
            '/\b0x[0-9a-f]+\b/i' => '<hex>',
            '/\b[0-9a-f]{8,}\b/i' => '<hex>',
            '/"[^"]*"|\'[^\']*\'/' => '<str>',
            '/(?<![A-Za-z_])\d+(?:\.\d+)?/' => '<num>',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $message);
    }
}
