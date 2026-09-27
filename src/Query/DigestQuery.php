<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Issue;
use App\Enum\IssueStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DigestQuery
{
    public const int WINDOW_HOURS = 24;
    private const int LIMIT = 20;
    private const int TOP = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $to = $this->clock->now();
        $from = $to->modify(\sprintf('-%d hours', self::WINDOW_HOURS));
        $recentEvents = $this->recentEventCounts($from);

        return [
            'generated_at' => $to->format(\DATE_ATOM),
            'window' => ['from' => $from->format(\DATE_ATOM), 'to' => $to->format(\DATE_ATOM), 'hours' => self::WINDOW_HOURS],
            'totals' => [
                'events' => array_sum($recentEvents),
                'open_issues' => $this->openIssues(),
            ],
            'new_issues' => $this->present($this->issuesSince('firstSeen', $from), $recentEvents),
            'regressions' => $this->present($this->issuesSince('regressedAt', $from), $recentEvents),
            'top_issues' => $this->present($this->loadIssues(array_slice(array_keys($recentEvents), 0, self::TOP)), $recentEvents),
        ];
    }

    /**
     * @return list<Issue>
     */
    private function issuesSince(string $field, \DateTimeImmutable $from): array
    {
        /** @var list<Issue> $issues */
        $issues = $this->entityManager->createQueryBuilder()
            ->select('i', 'p')
            ->from(Issue::class, 'i')
            ->join('i.project', 'p')
            ->where(\sprintf('i.%s >= :from', $field))
            ->setParameter('from', $from)
            ->orderBy('i.'.$field, 'DESC')
            ->setMaxResults(self::LIMIT)
            ->getQuery()
            ->getResult();

        return $issues;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Issue>
     */
    private function loadIssues(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Issue> $issues */
        $issues = $this->entityManager->createQueryBuilder()
            ->select('i', 'p')
            ->from(Issue::class, 'i')
            ->join('i.project', 'p')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $order = array_flip($ids);
        usort($issues, static fn (Issue $a, Issue $b): int => $order[(int) $a->getId()] <=> $order[(int) $b->getId()]);

        return $issues;
    }

    /**
     * @return array<int, int>
     */
    private function recentEventCounts(\DateTimeImmutable $from): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT issue_id, COUNT(*) AS events FROM event WHERE received_at >= :from GROUP BY issue_id ORDER BY events DESC, issue_id DESC',
            ['from' => $from->format('Y-m-d H:i:s')],
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['issue_id']] = (int) $row['events'];
        }

        return $counts;
    }

    private function openIssues(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM issue WHERE status = :status', ['status' => IssueStatus::Unresolved->value]);
    }

    /**
     * @param list<Issue>     $issues
     * @param array<int, int> $recentEvents
     *
     * @return list<array<string, mixed>>
     */
    private function present(array $issues, array $recentEvents): array
    {
        return array_map(fn (Issue $issue): array => [
            'id' => $issue->getId(),
            'project' => ['slug' => $issue->getProject()->getSlug(), 'name' => $issue->getProject()->getName()],
            'title' => $issue->getTitle(),
            'culprit' => $issue->getCulprit(),
            'level' => $issue->getLevel()->value,
            'status' => $issue->getStatus()->value,
            'events' => $issue->getEventCount(),
            'events_in_window' => $recentEvents[(int) $issue->getId()] ?? 0,
            'first_seen' => $issue->getFirstSeen()->format(\DATE_ATOM),
            'last_seen' => $issue->getLastSeen()->format(\DATE_ATOM),
            'url' => $this->urls->generate('app_issue', ['slug' => $issue->getProject()->getSlug(), 'id' => $issue->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ], $issues);
    }
}
