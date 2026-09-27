<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\IssueStatus;
use App\Enum\Level;
use PHPUnit\Framework\TestCase;

final class IssueTest extends TestCase
{
    public function testRecordsEventsAndDetectsRegressions(): void
    {
        $issue = $this->issue();

        self::assertFalse($issue->recordEvent('New title', 'culprit', Level::Fatal, 'v2', new \DateTimeImmutable('2026-09-02')));
        self::assertSame(1, $issue->getEventCount());
        self::assertSame('New title', $issue->getTitle());
        self::assertSame('culprit', $issue->getCulprit());
        self::assertSame(Level::Fatal, $issue->getLevel());
        self::assertSame('v2', $issue->getLastRelease());
        self::assertEquals(new \DateTimeImmutable('2026-09-02'), $issue->getLastSeen());

        $issue->recordEvent('New title', null, Level::Error, null, new \DateTimeImmutable('2026-08-01'));
        self::assertEquals(new \DateTimeImmutable('2026-08-01'), $issue->getFirstSeen());
        self::assertEquals(new \DateTimeImmutable('2026-09-02'), $issue->getLastSeen());
        self::assertSame('v2', $issue->getLastRelease());

        $issue->resolve();
        self::assertSame(IssueStatus::Resolved, $issue->getStatus());
        self::assertNull($issue->getRegressedAt());
        self::assertTrue($issue->recordEvent('t', null, Level::Error, null, new \DateTimeImmutable('2026-09-03')));
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());
        self::assertEquals(new \DateTimeImmutable('2026-09-03'), $issue->getRegressedAt());
    }

    public function testIgnoredIssuesStayIgnored(): void
    {
        $issue = $this->issue();
        $issue->ignore();

        self::assertFalse($issue->recordEvent('t', null, Level::Error, null, new \DateTimeImmutable('2026-09-03')));
        self::assertSame(IssueStatus::Ignored, $issue->getStatus());

        $issue->reopen();
        self::assertSame(IssueStatus::Unresolved, $issue->getStatus());
    }

    public function testTracksNotifications(): void
    {
        $issue = $this->issue();
        self::assertNull($issue->getLastNotifiedAt());

        $issue->markNotified(new \DateTimeImmutable('2026-09-03 10:00'));
        self::assertEquals(new \DateTimeImmutable('2026-09-03 10:00'), $issue->getLastNotifiedAt());
        self::assertSame('abc', $issue->getFingerprint());
        self::assertSame('Shop', $issue->getProject()->getName());
        self::assertNull($issue->getId());
    }

    private function issue(): Issue
    {
        $project = new Project('Shop', 'shop', str_repeat('a', 32), new \DateTimeImmutable('2026-01-01'));

        return new Issue($project, 'abc', 'Title', null, Level::Error, new \DateTimeImmutable('2026-09-01'));
    }
}
