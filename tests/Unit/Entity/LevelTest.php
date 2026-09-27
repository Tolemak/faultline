<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Enum\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LevelTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, Level}>
     */
    public static function sentryLevels(): iterable
    {
        yield 'debug' => ['debug', Level::Debug];
        yield 'info' => ['INFO', Level::Info];
        yield 'log' => ['log', Level::Info];
        yield 'warning' => ['warning', Level::Warning];
        yield 'warn' => [' warn ', Level::Warning];
        yield 'error' => ['error', Level::Error];
        yield 'fatal' => ['fatal', Level::Fatal];
        yield 'critical' => ['critical', Level::Fatal];
        yield 'unknown' => ['loud', Level::Error];
        yield 'not a string' => [3, Level::Error];
    }

    #[DataProvider('sentryLevels')]
    public function testMapsSentryLevels(mixed $input, Level $expected): void
    {
        self::assertSame($expected, Level::fromSentry($input));
    }

    public function testMagnitudesGrowWithSeverity(): void
    {
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (Level $level): int => $level->magnitude(), Level::cases()));
    }
}
