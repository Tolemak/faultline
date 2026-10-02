<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Issue;
use App\Repository\IssueRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(IssueNotifierInterface::class)]
final readonly class ThrottledIssueNotifier implements IssueNotifierInterface
{
    public const int INTERVAL_SECONDS = 3600;

    public function __construct(
        #[Autowire(service: TelegramNotifier::class)]
        private IssueNotifierInterface $inner,
        private IssueRepository $issues,
        private ClockInterface $clock,
    ) {
    }

    public function notify(Issue $issue, IssueChange $change): void
    {
        $now = $this->clock->now();
        $last = $issue->getLastNotifiedAt();

        if (null !== $last && $now->getTimestamp() - $last->getTimestamp() < self::INTERVAL_SECONDS) {
            return;
        }

        $this->inner->notify($issue, $change);
        $this->issues->markNotified($issue, $now);
    }
}
