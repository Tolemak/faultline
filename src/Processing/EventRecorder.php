<?php

declare(strict_types=1);

namespace App\Processing;

use App\Entity\Event;
use App\Entity\Issue;
use App\Entity\Project;
use App\Notification\IssueChange;
use App\Repository\EventRepository;
use App\Repository\IssueDailyCountRepository;
use App\Repository\IssueRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueRepository $issues,
        private EventRepository $events,
        private IssueDailyCountRepository $dailyCounts,
    ) {
    }

    public function record(Project $project, NormalizedEvent $event, string $fingerprint, IssueSummary $summary, \DateTimeImmutable $receivedAt): ?RecordedEvent
    {
        return $this->entityManager->wrapInTransaction(function () use ($project, $event, $fingerprint, $summary, $receivedAt): ?RecordedEvent {
            if ($this->events->existsForProject($project, $event->eventId)) {
                return null;
            }

            $issue = $this->issues->findOneByFingerprint($project, $fingerprint);
            $change = null;
            if (null === $issue) {
                $issue = new Issue($project, $fingerprint, $summary->title, $summary->culprit, $event->level, $event->occurredAt);
                $this->entityManager->persist($issue);
                $change = IssueChange::New;
            }

            if ($issue->recordEvent($summary->title, $summary->culprit, $event->level, $event->release, $event->occurredAt)) {
                $change = IssueChange::Regression;
            }

            $this->entityManager->persist(new Event($project, $issue, $event, $receivedAt));
            $this->entityManager->flush();

            $this->dailyCounts->increment($issue, $receivedAt);

            return new RecordedEvent($issue, $change);
        });
    }
}
