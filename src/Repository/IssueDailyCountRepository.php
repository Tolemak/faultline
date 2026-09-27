<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\IssueDailyCount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueDailyCount>
 */
class IssueDailyCountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueDailyCount::class);
    }

    public function increment(Issue $issue, \DateTimeImmutable $day): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO issue_daily_count (issue_id, day, count) VALUES (:issue, :day, 1)
             ON CONFLICT (issue_id, day) DO UPDATE SET count = issue_daily_count.count + 1',
            ['issue' => $issue->getId(), 'day' => $day->format('Y-m-d')],
            ['issue' => ParameterType::INTEGER, 'day' => ParameterType::STRING],
        );
    }
}
