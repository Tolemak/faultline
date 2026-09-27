<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Event;
use App\Entity\Issue;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventQuery
{
    private const int TAG_KEYS = 8;
    private const int TAG_VALUES = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    public function count(Issue $issue): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Event::class, 'e')
            ->where('e.issue = :issue')
            ->setParameter('issue', $issue)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function nth(Issue $issue, int $offset): ?Event
    {
        /** @var Event|null $event */
        $event = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.issue = :issue')
            ->setParameter('issue', $issue)
            ->orderBy('e.receivedAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $event;
    }

    /**
     * @return array<string, list<array{value: string, count: int, share: float}>>
     */
    public function tagBreakdown(Issue $issue): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT t.key, t.value, COUNT(*) AS hits
                FROM event e, jsonb_each_text(CASE WHEN jsonb_typeof(e.tags) = 'object' THEN e.tags ELSE '{}'::jsonb END) AS t(key, value)
                WHERE e.issue_id = :issue
                GROUP BY t.key, t.value
                ORDER BY t.key, hits DESC, t.value
                SQL,
            ['issue' => $issue->getId()],
        );

        $totals = [];
        $grouped = [];
        foreach ($rows as $row) {
            $key = (string) $row['key'];
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row['hits'];
            if (\count($grouped[$key] ?? []) < self::TAG_VALUES) {
                $grouped[$key][] = ['value' => (string) $row['value'], 'count' => (int) $row['hits']];
            }
        }

        $breakdown = [];
        foreach (\array_slice($grouped, 0, self::TAG_KEYS, true) as $key => $values) {
            $breakdown[$key] = array_map(
                static fn (array $value): array => $value + ['share' => $value['count'] / $totals[$key]],
                $values,
            );
        }

        return $breakdown;
    }
}
