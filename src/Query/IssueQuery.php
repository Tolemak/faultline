<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Event;
use App\Entity\Issue;
use App\Entity\IssueDailyCount;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;

final readonly class IssueQuery
{
    public const int TREND_DAYS = 14;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function search(Project $project, IssueCriteria $criteria): IssuePage
    {
        $builder = $this->filtered($project, $criteria);

        $total = (int) (clone $builder)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / IssueCriteria::PER_PAGE));
        $page = min($criteria->page, $pages);

        $order = match ($criteria->sort) {
            'events' => 'i.eventCount',
            'first_seen' => 'i.firstSeen',
            default => 'i.lastSeen',
        };

        /** @var list<Issue> $issues */
        $issues = $builder
            ->orderBy($order, 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setFirstResult(($page - 1) * IssueCriteria::PER_PAGE)
            ->setMaxResults(IssueCriteria::PER_PAGE)
            ->getQuery()
            ->getResult();

        return new IssuePage($issues, $this->trends($issues), $total, $page, $pages);
    }

    /**
     * @return array{environments: list<string>, releases: list<string>}
     */
    public function filterOptions(Project $project): array
    {
        return [
            'environments' => $this->distinctEventValues($project, 'environment'),
            'releases' => $this->distinctEventValues($project, 'release'),
        ];
    }

    /**
     * @param list<Issue> $issues
     *
     * @return array<int, list<int>>
     */
    public function trends(array $issues): array
    {
        if ([] === $issues) {
            return [];
        }

        $today = $this->clock->now()->setTime(0, 0);
        $since = $today->modify(\sprintf('-%d days', self::TREND_DAYS - 1));

        /** @var list<array{issueId: int, day: \DateTimeImmutable, count: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(c.issue) AS issueId', 'c.day', 'c.count')
            ->from(IssueDailyCount::class, 'c')
            ->where('c.issue IN (:issues)')
            ->andWhere('c.day >= :since')
            ->setParameter('issues', $issues)
            ->setParameter('since', $since->format('Y-m-d'))
            ->getQuery()
            ->getArrayResult();

        $trends = [];
        foreach ($issues as $issue) {
            $trends[(int) $issue->getId()] = array_fill(0, self::TREND_DAYS, 0);
        }
        foreach ($rows as $row) {
            $offset = (int) $since->diff($row['day'])->format('%a');
            if (isset($trends[(int) $row['issueId']][$offset])) {
                $trends[(int) $row['issueId']][$offset] = (int) $row['count'];
            }
        }

        return $trends;
    }

    private function filtered(Project $project, IssueCriteria $criteria): QueryBuilder
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Issue::class, 'i')
            ->where('i.project = :project')
            ->setParameter('project', $project);

        if (null !== $criteria->status) {
            $builder->andWhere('i.status = :status')->setParameter('status', $criteria->status);
        }
        if (null !== $criteria->level) {
            $builder->andWhere('i.level = :level')->setParameter('level', $criteria->level);
        }
        if (null !== $criteria->search) {
            $builder
                ->andWhere('LOWER(i.title) LIKE :search OR LOWER(i.culprit) LIKE :search')
                ->setParameter('search', '%'.addcslashes(mb_strtolower($criteria->search), '%_\\').'%');
        }
        foreach (['environment' => $criteria->environment, 'release' => $criteria->release] as $field => $value) {
            if (null === $value) {
                continue;
            }
            $builder
                ->andWhere(\sprintf('EXISTS (SELECT e_%2$s.id FROM %1$s e_%2$s WHERE e_%2$s.issue = i AND e_%2$s.%2$s = :%2$s)', Event::class, $field))
                ->setParameter($field, $value);
        }

        return $builder;
    }

    /**
     * @return list<string>
     */
    private function distinctEventValues(Project $project, string $field): array
    {
        /** @var list<array{value: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(\sprintf('DISTINCT e.%s AS value', $field))
            ->from(Event::class, 'e')
            ->where('e.project = :project')
            ->andWhere(\sprintf('e.%s IS NOT NULL', $field))
            ->setParameter('project', $project)
            ->orderBy('value', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['value'], $rows);
    }
}
