<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\Level;
use App\Notification\IssueChange;
use App\Notification\ThrottledIssueNotifier;
use App\Tests\Support\NotifierSpy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ThrottledIssueNotifierTest extends TestCase
{
    public function testSendsAtMostOncePerHourPerIssue(): void
    {
        $spy = new NotifierSpy();
        $clock = new MockClock('2026-09-27 10:00:00');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $notifier = new ThrottledIssueNotifier($spy, $entityManager, $clock);
        $issue = $this->issue();
        $other = $this->issue();

        $notifier->notify($issue, IssueChange::New);
        $clock->modify('+59 minutes');
        $notifier->notify($issue, IssueChange::Regression);
        $notifier->notify($other, IssueChange::New);
        $clock->modify('+1 minute');
        $notifier->notify($issue, IssueChange::Regression);

        self::assertSame([IssueChange::New, IssueChange::New, IssueChange::Regression], array_map(static fn (array $call): IssueChange => $call[1], $spy->calls));
        self::assertEquals(new \DateTimeImmutable('2026-09-27 11:00:00'), $issue->getLastNotifiedAt());
    }

    private function issue(): Issue
    {
        return new Issue(new Project('Shop', 'shop', str_repeat('a', 32), new \DateTimeImmutable()), 'f', 'Boom', null, Level::Error, new \DateTimeImmutable());
    }
}
