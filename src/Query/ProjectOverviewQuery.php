<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Project;
use App\Enum\IssueStatus;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final readonly class ProjectOverviewQuery
{
    public const int HOURS = 24;

    public function __construct(
        private ProjectRepository $projects,
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<ProjectOverview>
     */
    public function all(): array
    {
        $projects = $this->projects->findAllOrdered();
        if ([] === $projects) {
            return [];
        }

        $ids = array_map(static fn (Project $project): int => (int) $project->getId(), $projects);
        $open = $this->openIssues($ids);
        $hourly = $this->hourly($ids);

        return array_map(
            static fn (Project $project): ProjectOverview => new ProjectOverview(
                $project,
                $open[(int) $project->getId()] ?? 0,
                $hourly[(int) $project->getId()] ?? array_fill(0, self::HOURS, 0),
            ),
            $projects,
        );
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function openIssues(array $ids): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT project_id, COUNT(*) AS open FROM issue WHERE project_id IN (:ids) AND status = :status GROUP BY project_id',
            ['ids' => $ids, 'status' => IssueStatus::Unresolved->value],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $open = [];
        foreach ($rows as $row) {
            $open[(int) $row['project_id']] = (int) $row['open'];
        }

        return $open;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, list<int>>
     */
    private function hourly(array $ids): array
    {
        $end = $this->clock->now();
        $start = $end->setTime((int) $end->format('H'), 0)->modify(\sprintf('-%d hours', self::HOURS - 1));

        $rows = $this->connection->fetchAllAssociative(
            "SELECT project_id, date_trunc('hour', received_at) AS hour, COUNT(*) AS events
             FROM event WHERE project_id IN (:ids) AND received_at >= :start
             GROUP BY project_id, hour",
            ['ids' => $ids, 'start' => $start->format('Y-m-d H:i:s')],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $hourly = [];
        foreach ($rows as $row) {
            $offset = intdiv((new \DateTimeImmutable((string) $row['hour'], $start->getTimezone()))->getTimestamp() - $start->getTimestamp(), 3600);
            if ($offset >= 0 && $offset < self::HOURS) {
                $projectId = (int) $row['project_id'];
                $hourly[$projectId] ??= array_fill(0, self::HOURS, 0);
                $hourly[$projectId][$offset] = (int) $row['events'];
            }
        }

        return $hourly;
    }
}
