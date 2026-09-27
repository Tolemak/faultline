<?php

declare(strict_types=1);

namespace App\Processing;

use App\Enum\Level;

final readonly class NormalizedEvent
{
    /**
     * @param list<array{type: ?string, value: ?string, module: ?string, mechanism: ?array<string, mixed>, frames: list<array<string, mixed>>}> $exceptions
     * @param array<string, string>                                                                                                             $tags
     * @param array<string, mixed>                                                                                                              $contexts
     * @param array<string, mixed>|null                                                                                                         $request
     * @param array<string, mixed>|null                                                                                                         $user
     * @param array<string, mixed>                                                                                                              $extra
     * @param list<string>|null                                                                                                                 $fingerprint
     */
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $occurredAt,
        public Level $level,
        public string $platform,
        public ?string $environment,
        public ?string $release,
        public ?string $transaction,
        public ?string $message,
        public ?string $messageTemplate,
        public array $exceptions,
        public array $tags,
        public array $contexts,
        public ?array $request,
        public ?array $user,
        public array $extra,
        public ?array $fingerprint,
    ) {
    }
}
