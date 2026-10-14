<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

final class LoggerSpy extends AbstractLogger
{
    /**
     * @var list<array{string, array<mixed>}>
     */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $message, $context];
    }
}
