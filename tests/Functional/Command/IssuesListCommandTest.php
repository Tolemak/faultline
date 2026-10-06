<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\IssuesListCommand;
use App\Tests\Support\EventSeeder;
use App\Tests\Support\ProjectFixtures;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class IssuesListCommandTest extends KernelTestCase
{
    use EventSeeder;
    use ProjectFixtures;

    public function testListsIssuesAsJsonWithoutPayloads(): void
    {
        $tester = $this->seededTester();

        $tester->execute(['--format' => 'json']);

        $tester->assertCommandIsSuccessful();
        $rows = self::rows($tester);
        self::assertCount(3, $rows);
        self::assertSame('Fresh', $rows[0]['title']);
        self::assertSame('shop', $rows[0]['project']);
        self::assertSame(['project', 'id', 'title', 'culprit', 'level', 'firstSeen', 'lastSeen', 'events', 'status'], array_keys($rows[0]));
        self::assertStringNotContainsString('secret', $tester->getDisplay());
    }

    public function testFiltersByProjectSinceStatusAndLimit(): void
    {
        $tester = $this->seededTester();

        self::assertSame(1, $this->issueCount($tester, ['--project' => 'blog']));
        self::assertSame(2, $this->issueCount($tester, ['--since' => '24h']));
        self::assertSame(2, $this->issueCount($tester, ['--since' => '24h', '--new' => true]));
        self::assertSame(0, $this->issueCount($tester, ['--status' => 'resolved']));
        self::assertSame(3, $this->issueCount($tester, ['--status' => 'unresolved']));
        self::assertSame(1, $this->issueCount($tester, ['--limit' => '1']));
    }

    public function testPrintsTable(): void
    {
        $tester = $this->seededTester();

        $tester->execute(['--project' => 'shop']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Fresh', $tester->getDisplay());
        self::assertStringContainsString('unresolved', $tester->getDisplay());
    }

    public function testRejectsInvalidOptions(): void
    {
        $tester = $this->seededTester();

        self::assertSame(Command::FAILURE, $tester->execute(['--project' => 'missing']));
        self::assertSame(Command::FAILURE, $tester->execute(['--status' => 'nope']));
        self::assertSame(Command::FAILURE, $tester->execute(['--since' => 'yesterday-ish??']));
        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '0']));
        self::assertSame(Command::FAILURE, $tester->execute(['--format' => 'xml']));
    }

    public function testParsesRelativeAndAbsoluteSince(): void
    {
        $now = new \DateTimeImmutable('2026-10-06 12:00:00 UTC');

        self::assertSame('2026-10-05T12:00:00+00:00', IssuesListCommand::parseSince('24h', $now)?->format(\DATE_ATOM));
        self::assertSame('2026-09-29T12:00:00+00:00', IssuesListCommand::parseSince('7d', $now)?->format(\DATE_ATOM));
        self::assertSame('2026-10-06T11:30:00+00:00', IssuesListCommand::parseSince('30m', $now)?->format(\DATE_ATOM));
        self::assertSame('2026-10-01T00:00:00+00:00', IssuesListCommand::parseSince('2026-10-01T00:00:00+00:00', $now)?->format(\DATE_ATOM));
        self::assertNull(IssuesListCommand::parseSince('not a date', $now));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(CommandTester $tester): array
    {
        $rows = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($rows) && array_is_list($rows));

        return $rows;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function issueCount(CommandTester $tester, array $options): int
    {
        $tester->execute($options + ['--format' => 'json']);

        return \count(self::rows($tester));
    }

    private function seededTester(): CommandTester
    {
        self::bootKernel();
        $container = self::getContainer();
        $shop = self::createProject($container, 'Shop');
        $blog = self::createProject($container, 'Blog');

        self::seedEvent($container, $shop, self::exceptionEvent('RuntimeException', 'Old', 'oldFn'), new \DateTimeImmutable('-5 days'));
        self::seedEvent($container, $shop, ['message' => 'Fresh'], new \DateTimeImmutable('-1 hour'));
        self::seedEvent($container, $blog, self::exceptionEvent('LogicException', 'Recent', 'recentFn'), new \DateTimeImmutable('-3 hours'));

        return new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('faultline:issues:list'));
    }
}
