<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CheckCoverageScriptTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures/clover/';

    public function testPassesAtOrAboveMinimum(): void
    {
        $process = $this->runScript(self::FIXTURES.'covered.xml', '90');

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Line coverage 90.00% (9/10), minimum 90.00%.', $process->getOutput());
    }

    public function testFailsBelowMinimum(): void
    {
        $process = $this->runScript(self::FIXTURES.'covered.xml', '90.5');

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Line coverage 90.00%', $process->getErrorOutput());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'missing arguments' => [[], 'Usage:'];
        yield 'non numeric minimum' => [[self::FIXTURES.'covered.xml', 'abc'], 'Minimum must be a number'];
        yield 'minimum above 100' => [[self::FIXTURES.'covered.xml', '101'], 'Minimum must be a number'];
        yield 'missing file' => [[self::FIXTURES.'missing.xml', '80'], 'not found'];
        yield 'broken xml' => [[self::FIXTURES.'broken.xml', '80'], 'is not valid XML'];
        yield 'no metrics' => [[self::FIXTURES.'no-metrics.xml', '80'], 'has no project metrics'];
        yield 'no statements' => [[self::FIXTURES.'empty.xml', '80'], 'contains no statements'];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(array $arguments, string $message): void
    {
        $process = $this->runScript(...$arguments);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString($message, $process->getErrorOutput());
    }

    private function runScript(string ...$arguments): Process
    {
        $process = new Process([\PHP_BINARY, \dirname(__DIR__, 2).'/bin/check-coverage.php', ...$arguments]);
        $process->run();

        return $process;
    }
}
