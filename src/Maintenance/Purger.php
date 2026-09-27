<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Repository\ProjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;

final readonly class Purger
{
    public function __construct(
        private ProjectRepository $projects,
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function purge(): PurgeResult
    {
        $events = 0;
        foreach ($this->projects->findAllOrdered() as $project) {
            $cutoff = $this->clock->now()->modify(\sprintf('-%d days', $project->getRetentionDays()));
            $events += (int) $this->connection->executeStatement(
                'DELETE FROM event WHERE project_id = :project AND received_at < :cutoff',
                ['project' => $project->getId(), 'cutoff' => $cutoff->format('Y-m-d H:i:s')],
                ['project' => ParameterType::INTEGER],
            );
            $this->connection->executeStatement(
                'DELETE FROM issue_daily_count c USING issue i WHERE c.issue_id = i.id AND i.project_id = :project AND c.day < :cutoff',
                ['project' => $project->getId(), 'cutoff' => $cutoff->format('Y-m-d')],
                ['project' => ParameterType::INTEGER],
            );
        }

        $issues = (int) $this->connection->executeStatement(
            'DELETE FROM issue i WHERE NOT EXISTS (SELECT 1 FROM event e WHERE e.issue_id = i.id)',
        );

        return new PurgeResult($events, $issues);
    }
}
