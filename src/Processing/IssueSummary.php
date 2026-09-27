<?php

declare(strict_types=1);

namespace App\Processing;

use App\Entity\Issue;

final readonly class IssueSummary
{
    public function __construct(
        public string $title,
        public ?string $culprit,
    ) {
    }

    public static function of(NormalizedEvent $event): self
    {
        return new self(self::title($event), self::culprit($event));
    }

    private static function title(NormalizedEvent $event): string
    {
        $exception = [] === $event->exceptions ? null : $event->exceptions[array_key_last($event->exceptions)];

        $title = match (true) {
            null !== $exception && null !== $exception['type'] && null !== $exception['value'] => $exception['type'].': '.$exception['value'],
            null !== $exception && null !== $exception['type'] => $exception['type'],
            null !== $exception && null !== $exception['value'] => $exception['value'],
            null !== $event->message => $event->message,
            default => '<unlabeled event>',
        };

        return mb_substr(strtok($title, "\n") ?: $title, 0, Issue::TITLE_LENGTH);
    }

    private static function culprit(NormalizedEvent $event): ?string
    {
        if (null !== $event->transaction) {
            return mb_substr($event->transaction, 0, Issue::CULPRIT_LENGTH);
        }

        $exception = [] === $event->exceptions ? null : $event->exceptions[array_key_last($event->exceptions)];
        if (null === $exception || [] === $exception['frames']) {
            return null;
        }

        $frames = $exception['frames'];
        $inApp = array_values(array_filter($frames, static fn (array $frame): bool => true === ($frame['in_app'] ?? null)));
        $candidates = [] === $inApp ? $frames : $inApp;
        $frame = $candidates[array_key_last($candidates)];

        $location = $frame['module'] ?? $frame['filename'] ?? null;
        $function = $frame['function'] ?? null;
        $culprit = trim((\is_string($location) ? $location : '').(\is_string($function) ? ' in '.$function : ''));

        return '' === $culprit ? null : mb_substr($culprit, 0, Issue::CULPRIT_LENGTH);
    }
}
